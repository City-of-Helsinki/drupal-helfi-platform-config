<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_translation\ContentTranslationManager;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\content_translation\Controller\ContentTranslationController;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\helfi_platform_config\Translation\OutdatedTranslationChecker;

/**
 * Adds the last edit time and outdated notices to the translations overview.
 */
class TranslationOverviewController extends ContentTranslationController {

  public function __construct(
    ContentTranslationManagerInterface $manager,
    EntityFieldManagerInterface $entityFieldManager,
    TimeInterface $time,
    protected readonly OutdatedTranslationChecker $outdatedTranslationChecker,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($manager, $entityFieldManager, $time);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    $build = parent::overview($route_match, $entity_type_id);

    return $this->addUpdatedColumn($build, $route_match->getParameter((string) $entity_type_id));
  }

  /**
   * Adds the column that shows when each translation was last edited.
   *
   * @param array<string, mixed> $build
   *   The overview built by the parent.
   * @param mixed $entity
   *   The entity of the overview.
   *
   * @return array<string, mixed>
   *   The overview.
   */
  protected function addUpdatedColumn(array $build, mixed $entity): array {
    $header = $build['content_translation_overview']['#header'] ?? NULL;
    $rows = $build['content_translation_overview']['#rows'] ?? NULL;

    // The parent adds one row per language, in this order. Leave the table
    // as it is if the rows cannot be matched to the languages.
    $langcodes = array_map('strval', array_keys($this->languageManager()->getLanguages()));

    if (
      !$entity instanceof ContentEntityInterface ||
      !$entity->hasField(OutdatedTranslationChecker::FIELD) ||
      !is_array($header) ||
      !is_array($rows) ||
      !array_is_list($rows) ||
      count($rows) !== count($langcodes) ||
      array_filter($rows, 'is_array') !== $rows
    ) {
      return $build;
    }

    $this->insertCell($header, $this->t('Updated'));

    foreach ($langcodes as $delta => $langcode) {
      $translation = $this->loadTranslation($entity, $langcode);
      $this->insertCell($rows[$delta], $translation ? $this->buildUpdatedCell($translation) : '');
    }

    $build['content_translation_overview']['#header'] = $header;
    $build['content_translation_overview']['#rows'] = $rows;

    return $build;
  }

  /**
   * Loads the translation that the parent lists on the language's row.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The default revision of the entity.
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The translation, or NULL if the entity is not translated to the
   *   language.
   */
  protected function loadTranslation(ContentEntityInterface $entity, string $langcode): ?ContentEntityInterface {
    // With pending revisions, the parent lists the latest revision that
    // affects each translation.
    if (ContentTranslationManager::isPendingRevisionSupportEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $storage = $this->entityTypeManager()->getStorage($entity->getEntityTypeId());
      assert($storage instanceof ContentEntityStorageInterface);
      $revisionId = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $langcode);
      $revision = $revisionId ? $storage->loadRevision($revisionId) : NULL;

      if ($revision instanceof ContentEntityInterface && (!$revision->wasDefaultRevision() || $entity->hasTranslation($langcode))) {
        $entity = $revision;
      }
    }

    return $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
  }

  /**
   * Builds the cell that shows when the translation was last edited.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation.
   *
   * @return array<string, mixed>|string
   *   The table cell.
   */
  protected function buildUpdatedCell(ContentEntityInterface $translation): array|string {
    $changed = $this->outdatedTranslationChecker->getChangedTime($translation);

    if ($changed === NULL) {
      return '';
    }

    $days = $this->outdatedTranslationChecker->getOutdatedDays($translation);

    if ($days === NULL) {
      return $this->dateFormatter->format($changed, 'long');
    }

    return [
      'data' => [
        'notice' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['translation-state--outdated']],
          '#attached' => ['library' => ['hdbt/hdbt-icons']],
          'icon' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => [
              'class' => ['hel-icon', 'hel-icon--alert-circle-fill'],
              'aria-hidden' => 'true',
            ],
          ],
          'label' => [
            '#markup' => $this->t('Check the language version', [], ['context' => 'Outdated translation']),
          ],
        ],
        'changed' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('@date (@days days older than Finnish)', [
            '@date' => $this->dateFormatter->format($changed, 'short'),
            '@days' => $days,
          ], ['context' => 'Outdated translation']),
        ],
      ],
    ];
  }

  /**
   * Inserts a cell before the last cell (operations) of the row.
   *
   * @param array<int, mixed> $row
   *   The table row or header.
   * @param mixed $cell
   *   The cell.
   */
  protected function insertCell(array &$row, mixed $cell): void {
    array_splice($row, count($row) - 1, 0, [$cell]);
  }

}
