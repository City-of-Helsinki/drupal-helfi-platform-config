<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\helfi_recommendations\Client\ApiClient;
use Drupal\helfi_recommendations\Client\Keyword;
use Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface;

/**
 * The topic manager.
 */
final class TopicsManager implements TopicsManagerInterface {

  public const KEYWORD_VID = 'recommendation_topics';

  /**
   * List of items that have been processed in this request.
   *
   * @var array<string, TRUE>
   */
  private array $processedItems = [];

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\helfi_recommendations\Client\ApiClient $keywordGenerator
   *   The keyword generator.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ApiClient $keywordGenerator,
    private readonly QueueFactory $queueFactory,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function queueEntity(ContentEntityInterface $entity, bool $overwriteExisting = FALSE) : void {
    $topics = $this->getSuggestedTopicsEntities($entity, !$overwriteExisting);

    // Skip if the entity does not have topics or
    // entity was already processed in this request.
    if (empty($topics) || $this->isEntityProcessed($entity)) {
      return;
    }

    $this->queueFactory
      ->get('helfi_recommendations_queue')
      ->createItem([
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'language' => $entity->language()->getId(),
        'overwrite' => $overwriteExisting,
      ]);
  }

  /**
   * {@inheritDoc}
   */
  public function processEntity(ContentEntityInterface $entity, bool $overwriteExisting = FALSE) : void {
    $this->processEntities([$entity], $overwriteExisting);
  }

  /**
   * {@inheritDoc}
   */
  public function processEntities(array $entities, bool $overwriteExisting = FALSE) : void {
    foreach ($this->prepareBatches($entities, $overwriteExisting) as $batch) {
      $result = $this->keywordGenerator->suggestBatch($batch);

      // KeywordGenerator::suggestBatch preserves ids.
      foreach ($result as $id => $keywords) {
        if (!$keywords) {
          continue;
        }

        $topics = $this->getSuggestedTopicsEntities($batch[$id], $overwriteExisting);
        $this->saveKeywords($topics, $keywords, $batch[$id]->language());

        // Mark as processed so the same entity is bombarding the
        // API if it is queued multiple times for some reason.
        $this->processedItems[$this->getEntityKey($batch[$id])] = TRUE;
      }
    }
  }

  /**
   * Gets entities in batches.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   The entities.
   * @param bool $overwriteExisting
   *   Overwrites existing keywords when set to TRUE.
   *
   * @return \Generator
   *   Batch of entities.
   */
  private function prepareBatches(array $entities, bool $overwriteExisting) : \Generator {
    $buckets = [];

    foreach ($entities as $key => $entity) {
      $topics = $this->getSuggestedTopicsEntities($entity, $overwriteExisting);

      // Skip if the entity does not have topics or
      // entity was already processed in this request.
      if (empty($topics) || $this->isEntityProcessed($entity)) {
        continue;
      }

      // Keyword generator does not support mixing languages in one request,
      // so divide translations into buckets that are handled separately.
      // Each bucket size must be <= KeywordGenerator::MAX_BATCH_SIZE.
      if ($entity instanceof TranslatableInterface) {
        foreach ($entity->getTranslationLanguages() as $language) {
          $buckets[$language->getId()][$key] = $entity->getTranslation($language->getId());
        }
      }
      else {
        $buckets[$entity->language()->getId()][$key] = $entity;
      }
    }

    foreach ($buckets as $bucket) {
      foreach (array_chunk($bucket, ApiClient::MAX_BATCH_SIZE, preserve_keys: TRUE) as $batch) {
        yield $batch;
      }
    }
  }

  /**
   * Saves keywords to entity.
   *
   * @param \Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface[] $topics
   *   The topics entities.
   * @param \Drupal\helfi_recommendations\Client\Keyword[] $keywords
   *   Keywords.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Keyword language.
   *
   * @throws \Drupal\helfi_recommendations\RecommendationsException
   */
  private function saveKeywords(array $topics, array $keywords, LanguageInterface $language) : void {
    try {
      $values = array_map(fn (Keyword $keyword) => [
        'entity' => $this->getTerm($keyword, $language->getId()),
        'score' => $keyword->score,
      ], $keywords);

      foreach ($topics as $topic) {
        assert($topic instanceof SuggestedTopicsInterface);

        $topic->set('keywords', $values);
        $topic->save();
      }
    }
    catch (\Exception $e) {
      throw new RecommendationsException("Failed to save keywords.", $e->getCode(), previous: $e);
    }
  }

  /**
   * Gets or inserts taxonomy term that matches API result.
   *
   * @param \Drupal\helfi_recommendations\Client\Keyword $keyword
   *   Keyword.
   * @param string $langcode
   *   Term langcode.
   *
   * @throws \Exception
   */
  private function getTerm(Keyword $keyword, string $langcode) {
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    $terms = $termStorage->loadByProperties([
      'vid' => self::KEYWORD_VID,
      // Unique identifier for keyword.
      'field_uri' => $keyword->uri,
    ]);

    if ($term = reset($terms)) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      if ($term->hasTranslation($langcode)) {
        return $term->getTranslation($langcode);
      }

      $term = $term->addTranslation($langcode, [
        'vid' => self::KEYWORD_VID,
        'name' => $keyword->label,
        'langcode' => $langcode,
        'field_uri' => $keyword->uri,
      ]);
    }
    else {
      $term = $termStorage->create([
        'vid' => self::KEYWORD_VID,
        'name' => $keyword->label,
        'langcode' => $langcode,
        'field_uri' => $keyword->uri,
      ]);
    }

    $term->save();

    return $term;
  }

  /**
   * Gets key for $processedItems.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  private function getEntityKey(EntityInterface $entity) : string {
    return implode(":", [$entity->getEntityTypeId(), $entity->bundle(), $entity->language()->getId()]);
  }

  /**
   * Returns true if entity has been processed in this request.
   *
   * This can be used to prevent recursion if items are processed
   * in hook_entity_update.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  private function isEntityProcessed(EntityInterface $entity) : bool {
    return isset($this->processedItems[$this->getEntityKey($entity)]);
  }

  /**
   * Get SuggestedTopics entities linked to a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param bool $filterEmpty
   *   If TRUE, only return topic entities that have no keywords.
   *
   * @return \Drupal\helfi_recommendations\Entity\SuggestedTopicsInterface[]
   *   Linked topics entities.
   */
  private function getSuggestedTopicsEntities(ContentEntityInterface $entity, bool $filterEmpty): array {
    // List suggested_topics_reference fields that the entity has.
    $fields = array_filter(
      $entity->getFieldDefinitions(),
      static fn (FieldDefinitionInterface $definition) => $definition->getType() === 'suggested_topics_reference'
    );

    $topics = [];

    foreach ($fields as $key => $definition) {
      $field = $entity->get($key);
      assert($field instanceof EntityReferenceFieldItemListInterface);

      // Get all referenced topic entities from all
      // suggested_topics_reference fields.
      foreach ($field->referencedEntities() as $topic) {
        assert($topic instanceof SuggestedTopicsInterface);
        $topics[] = $topic;
      }
    }

    return array_filter($topics, static fn (SuggestedTopicsInterface $topic) => !$filterEmpty || !$topic->hasKeywords());
  }

}
