<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the suggested topics entity class.
 *
 * This entity stores the AI suggested topics with their scores. The background
 * tasks that generate the suggested topics should write to this entity instead
 * of the content itself in order to avoid conflicts.
 *
 * @ContentEntityType(
 *   id = "suggested_topics",
 *   label = @Translation("AI suggested topics for text"),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "suggested_topics",
 *   entity_keys = {
 *     "id" = "uuid",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *     "label" = "id",
 *   },
 * )
 */
class SuggestedTopics extends ContentEntityBase implements SuggestedTopicsInterface {

  use EntityPublishedTrait;
  use SuggestedTopicsTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += self::publishedBaseFieldDefinitions($entity_type);
    $fields += self::suggestedTopicsFields($entity_type);

    return $fields;
  }

}
