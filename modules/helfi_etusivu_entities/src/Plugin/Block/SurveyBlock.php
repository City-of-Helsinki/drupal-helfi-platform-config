<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\Surveys;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Provides a 'Surveys' block.
 */
#[Block(
  id: "surveys",
  admin_label: new TranslatableMarkup("Surveys"),
)]
final class SurveyBlock extends EtusivuEntityBlockBase {

  /**
   * Entity types supported by survey and the corresponding field names.
   */
  private const ENTITY_TYPE_FIELDS = [
    'node' => 'field_survey_content_pages',
  ];

  /**
   * {@inheritDoc}
   */
  protected function sortEntities($local, $remote) : array {
    $currentEntity = $this->getCurrentPageEntity(array_keys(self::ENTITY_TYPE_FIELDS));

    $surveys = array_merge($remote, $local);

    // Sort by publised_at time.
    usort($surveys, static function (NodeInterface $a, NodeInterface $b) {
      $weightA = $a->get('published_at')->value;
      $weightB = $b->get('published_at')->value;
      if ($weightA === $weightB) {
        return 0;
      }
      return $weightA < $weightB ? 1 : -1;
    });

    $referenceField = self::ENTITY_TYPE_FIELDS[$currentEntity?->getEntityTypeId()] ?? NULL;

    // Pick which survey to show.
    foreach ($surveys as $node) {
      // Check if the node should be shown at all pages.
      if ($node->get('field_survey_content_pages')->isEmpty()) {
        return [$node];
      }

      // Show survey if current page's entity is found
      // from the list of referenced entities.
      if (!empty($referenceField) && $this->hasReference($referenceField, $node, $currentEntity)) {
        return [$node];
      }
    }

    return [];
  }

  /**
   * {@inheritDoc}
   */
  protected function getLocalEntities() : array {
    $langcodes = $this->getContentLangcodes();

    $storage = $this->entityTypeManager->getStorage('node');

    // Get all published survey nodes.
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'survey')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $langcodes, 'IN')
      ->sort('published_at', 'DESC');

    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'survey');

    // Query only local nodes.
    if (isset($fields['field_publish_externally'])) {
      $query->condition('field_publish_externally', FALSE);
    }

    return array_values($storage->loadMultiple($query->execute()));
  }

  /**
   * {@inheritDoc}
   */
  protected function getRemoteEntities(): array {
    $entityStorage = $this->getExternalEntityStorage('helfi_surveys');
    $nodes = [];

    /** @var \Drupal\external_entities\Entity\ExternalEntityInterface $entity */
    foreach ($entityStorage->loadMultiple() as $entity) {
      $linkUrl = NULL;
      $linkText = NULL;
      if ($entity->hasField('survey_link_text')) {
        $linkText = $entity->get('survey_link_text')->value;
        $linkUrl = $entity->get('survey_link_url')->value;
      }

      // Create nodes for the block based on external entity data.
      $nodes[] = Node::create([
        'uuid' => $entity->get('uuid')->value,
        'type' => 'survey',
        'field_survey_link' => ['uri' => $linkUrl, 'title' => $linkText],
        'langcode' => $entity->get('langcode')->value,
        'body' => Xss::filter($entity->get('body')->value),
        'title' => Xss::filter($entity->get('title')->value),
        'status' => $entity->get('status')->value,
      ]);
    }

    return $nodes;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'url.path',
      'languages:language_content',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      Surveys::$customCacheTag,
      'node_list:survey',
    ]);
  }

}
