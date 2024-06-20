<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
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
   * {@inheritdoc}
   */
  public function build(): array {
    $currentEntity = $this->getCurrentPageEntity(array_keys(self::ENTITY_TYPE_FIELDS));

    if ($survey = $this->getSurvey($currentEntity)) {
      return $this
        ->entityTypeManager
        ->getViewBuilder('node')
        ->view($survey, 'default');
    }

    return [];
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

  /**
   * Loads most resent survey node that is valid for this page.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $currentEntity
   *   Entity on current page.
   *
   * @returns NodeInterface|null
   *   Most recent survey for this page or NULL if none exists.
   */
  public function getSurvey(?EntityInterface $currentEntity) : ?NodeInterface {
    try {
      // Since the arrays contain numeric keys, the
      // later values will be appended.
      $surveys = array_merge($this->getExternalSurveys(), $this->getLocalSurveys());
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return NULL;
    }

    // Sort by publised_at time.
    usort($surveys, static function (NodeInterface $a, NodeInterface $b) {
      $weightA = $a->get('published_at')->value;
      $weightB = $b->get('published_at')->value;
      if ($weightA === $weightB) {
        return 0;
      }
      // More urgent announcements render first.
      return $weightA < $weightB ? 1 : -1;
    });

    $referenceField = self::ENTITY_TYPE_FIELDS[$currentEntity?->getEntityTypeId()] ?? NULL;

    foreach ($surveys as $node) {
      // Check if the announcement should be shown at all pages.
      if ($node->get('field_survey_content_pages')->isEmpty()) {
        return $node;
      }

      // Show survey if current page's entity is found
      // from the list of referenced entities.
      if (!empty($referenceField) && $this->hasReference($referenceField, $node, $currentEntity)) {
        return $node;
      }
    }

    return NULL;
  }

  /**
   * Loads external survey nodes.
   *
   * @return array<int, \Drupal\node\NodeInterface>
   *   External surveys.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getExternalSurveys(): array {
    $entityStorage = $this->getExternalEntityStorage('helfi_surveys');
    $nodes = [];

    /** @var \Drupal\external_entities\ExternalEntityInterface $entity */
    foreach ($entityStorage->loadMultiple() as $entity) {
      $linkUrl = NULL;
      $linkText = NULL;
      if ($entity->hasField('survey_link_text')) {
        $linkText = $entity->get('survey_link_text')->value;
        $linkUrl = $entity->get('survey_link_url')->value;
      }

      // Create announcement nodes for the block based on external entity data.
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
   * Loads local surveys.
   *
   * @return array<int, \Drupal\node\NodeInterface>
   *   Local surveys.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getLocalSurveys() : array {
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

}
