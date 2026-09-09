<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_ai\AiSummaryFieldDefinition;

/**
 * Hook implementations for Helfi AI module related entities.
 */
class EntityHooks {

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

    return [
      'ai_summary' => AiSummaryFieldDefinition::create(),
    ];
  }

}
