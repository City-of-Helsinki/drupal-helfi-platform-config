<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Entity;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\redirect\Entity\Redirect;

/**
 * Publishable redirect.
 */
class PublishableRedirect extends Redirect implements EntityPublishedInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields[$entity_type->getKey('custom')] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Custom redirect'))
      ->setDefaultValue(FALSE);

    return $fields;
  }

  /**
   * Is custom redirect.
   *
   * @return bool
   *   FALSE if this redirect was created automatically by Drupal.
   */
  public function isCustom(): bool {
    return (bool) $this->getEntityKey('custom');
  }

}
