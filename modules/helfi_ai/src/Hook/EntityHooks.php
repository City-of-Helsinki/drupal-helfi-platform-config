<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for Helfi AI module related entities.
 */
class EntityHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_base_field_info().
   *
   * @phpstan-return array<string, \Drupal\Core\Field\BaseFieldDefinition>
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() !== 'node') {
      return [];
    }

    $fields['ai_summary'] = BaseFieldDefinition::create('text_long')
      ->setLabel($this->t('AI summary', options: ['context' => 'Helfi AI']))
      ->setDescription($this->t('AI-generated content summary as a bullet list. Edit before accepting.', options: ['context' => 'Helfi AI']))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
