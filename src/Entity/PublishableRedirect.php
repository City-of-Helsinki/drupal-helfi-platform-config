<?php

namespace Drupal\helfi_platform_config\Entity;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\redirect\Entity\Redirect;

/**
 * Publishable redirect.
 */
class PublishableRedirect extends Redirect implements EntityPublishedInterface {

  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += self::publishedBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
