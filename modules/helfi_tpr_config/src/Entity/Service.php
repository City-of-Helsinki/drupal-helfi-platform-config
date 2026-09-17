<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_tpr\Entity\Service as BaseService;

/**
 * A bundle class override for Service entities.
 */
class Service extends BaseService {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['hide_service_points'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Hide service units listing'))
      ->setDescription(new TranslatableMarkup('Select this if you link from the page to a filter search or another listing.'))
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
