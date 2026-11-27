<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\ToneCheck;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Diff\Diff;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\helfi_entity_task\EntityTaskStorage;
use Drupal\node\NodeInterface;

/**
 * Flags pages for a tone check and records completed checks.
 */
class ToneCheckPrecheck {

  private const string TASK = 'tone_check';

  private const array BUNDLES = ['page'];

  private const float CHANGE_THRESHOLD = 0.1;

  private const int BASELINE_LIFETIME = 86400;

  private const array TEXT_FIELD_TYPES = [
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ];

  /**
   * Languages of the editor saves in this request, keyed by entity UUID.
   *
   * @var array<string, string>
   */
  private array $editorSaves = [];

  public function __construct(
    private readonly ?EntityTaskStorage $taskStorage,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Checks whether the tone check task applies to the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return bool
   *   TRUE if the tone check is enabled for the node's bundle.
   */
  public function applies(NodeInterface $node): bool {
    return $this->taskStorage !== NULL
      && in_array($node->bundle(), self::BUNDLES, TRUE)
      && $this->configFactory->get('helfi_ai.settings')->get('enable_tone_check');
  }

  /**
   * Registers a save of the node translation made through the edit form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node translation being saved.
   */
  public function registerEditorSave(NodeInterface $node): void {
    $this->editorSaves[(string) $node->uuid()] = $node->language()->getId();
  }

  /**
   * Checks whether the tone check is pending for the node translation.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node translation.
   *
   * @return bool
   *   TRUE if the tone check is pending.
   */
  public function isPending(NodeInterface $node): bool {
    return $this->applies($node) && $this->taskStorage?->isPending($node, self::TASK);
  }

  /**
   * Marks the tone check of the node translation done.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node translation.
   */
  public function complete(NodeInterface $node): void {
    $this->taskStorage?->complete($node, self::TASK);
  }

  /**
   * Gets the cache tags of the node's tone check status.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getCacheTags(NodeInterface $node): array {
    return $this->taskStorage?->getCacheTags($node) ?? [];
  }

  /**
   * Evaluates the pre-check rules if the node was saved through its form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The saved node.
   */
  public function evaluate(NodeInterface $node): void {
    $langcode = $this->editorSaves[(string) $node->uuid()] ?? NULL;
    unset($this->editorSaves[(string) $node->uuid()]);

    if ($langcode === NULL || !$this->taskStorage || !$this->applies($node) || !$node->hasTranslation($langcode)) {
      return;
    }
    $translation = $node->getTranslation($langcode);

    if ($this->taskStorage->isPending($translation, self::TASK)) {
      return;
    }
    $original = $node->getOriginal();

    if (!$original instanceof NodeInterface || !$original->hasTranslation($langcode)) {
      $this->taskStorage->mark($translation, self::TASK);
      return;
    }
    $originalTranslation = $original->getTranslation($langcode);
    $lastEditorSave = $this->getLastEditorSave($originalTranslation);
    $key = $this->getBaselineKey($translation);

    if ($this->taskStorage->isCompletedSince($translation, self::TASK, $lastEditorSave)) {
      $this->getBaselineStore()->setWithExpire($key, $this->extractText($translation), self::BASELINE_LIFETIME);
      return;
    }

    if ($lastEditorSave < strtotime('-1 year', $this->time->getRequestTime())) {
      $this->taskStorage->mark($translation, self::TASK);
      return;
    }
    $text = $this->extractText($translation);
    $baseline = $this->getBaselineStore()->get($key);

    if (!is_string($baseline)) {
      $baseline = $this->extractText($originalTranslation);
      $this->getBaselineStore()->setWithExpire($key, $baseline, self::BASELINE_LIFETIME);
    }

    if ($this->getChangeRatio($baseline, $text) >= self::CHANGE_THRESHOLD) {
      $this->taskStorage->mark($translation, self::TASK);
    }
  }

  /**
   * Extracts the text of the configurable text fields of an entity translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity translation.
   *
   * @return string
   *   The plain text.
   */
  public function extractText(ContentEntityInterface $entity): string {
    $langcode = $entity->language()->getId();
    $parts = [];

    foreach ($entity->getFields(FALSE) as $field) {
      $definition = $field->getFieldDefinition();

      if ($definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }
      $type = $definition->getType();

      if (in_array($type, self::TEXT_FIELD_TYPES, TRUE)) {
        foreach ($field as $item) {
          $parts[] = strip_tags(str_replace('<', ' <', (string) $item->get('value')->getValue()));
        }
      }
      elseif ($type === 'entity_reference_revisions' && $field instanceof EntityReferenceFieldItemListInterface) {
        foreach ($field->referencedEntities() as $child) {
          if ($child instanceof ContentEntityInterface) {
            $parts[] = $this->extractText($child->hasTranslation($langcode) ? $child->getTranslation($langcode) : $child);
          }
        }
      }
    }
    return trim(implode(' ', $parts));
  }

  /**
   * Calculates the share of changed words between two texts.
   *
   * @param string $from
   *   The earlier text.
   * @param string $to
   *   The later text.
   *
   * @return float
   *   The share of changed words, from 0 to 1.
   */
  public function getChangeRatio(string $from, string $to): float {
    $fromWords = $this->splitWords($from);
    $toWords = $this->splitWords($to);
    $total = max(count($fromWords), count($toWords));

    if ($total === 0) {
      return 0.0;
    }
    $changed = 0;

    foreach ((new Diff($fromWords, $toWords))->getEdits() as $edit) {
      if ($edit->type !== 'copy') {
        $changed += max(count($edit->orig ?: []), count($edit->closing ?: []));
      }
    }
    return min(1.0, $changed / $total);
  }

  /**
   * Gets the time of the last editor save of a translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation before the current save.
   *
   * @return int
   *   The timestamp.
   */
  private function getLastEditorSave(ContentEntityInterface $translation): int {
    if ($translation->hasField('changed_at') && !$translation->get('changed_at')->isEmpty()) {
      return (int) $translation->get('changed_at')->value;
    }
    return $translation instanceof NodeInterface ? (int) $translation->getChangedTime() : 0;
  }

  /**
   * Splits text into words.
   *
   * @param string $text
   *   The text.
   *
   * @return string[]
   *   The words.
   */
  private function splitWords(string $text): array {
    return preg_split('/\s+/u', html_entity_decode($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
  }

  /**
   * Gets the key of a translation's baseline text.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation.
   *
   * @return string
   *   The key.
   */
  private function getBaselineKey(ContentEntityInterface $translation): string {
    return $translation->getEntityTypeId() . ':' . $translation->id() . ':' . $translation->language()->getId();
  }

  /**
   * Gets the store of baseline texts.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   *   The store.
   */
  private function getBaselineStore(): KeyValueStoreExpirableInterface {
    return $this->keyValueExpirableFactory->get('helfi_ai.tone_check_baseline');
  }

}
