<?php

declare(strict_types=1);

namespace Drupal\helfi_toc\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Entity hooks for HELfi Table of contents.
 */
class EntityHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_base_field_info().
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array<string, \Drupal\Core\Field\BaseFieldDefinition>
   *   The base field definitions.
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $entity_types = [
      'node',
      'tpr_service',
      'tpr_unit',
    ];

    if (!in_array($entity_type->id(), $entity_types)) {
      return [];
    }

    $configurable_form = in_array($entity_type->id(), ['tpr_service', 'tpr_unit']);

    $fields['toc_enabled'] = BaseFieldDefinition::create('boolean')
      ->setLabel($this->t('Table of contents'))
      ->setDescription($this->t('Enable checkbox to create automatic table of contents for the page.'))
      ->setDefaultValue(FALSE)
      ->setInitialValue(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'settings' => ['display_label' => TRUE],
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', $configurable_form)
      ->setDisplayConfigurable('view', TRUE);

    $fields['toc_title'] = BaseFieldDefinition::create('string')
      ->setLabel($this->t('Table of contents title'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 1,
      ])
      ->setDefaultValue($this->t('Table of contents'))
      ->setDisplayConfigurable('form', $configurable_form);

    return $fields;
  }

}
