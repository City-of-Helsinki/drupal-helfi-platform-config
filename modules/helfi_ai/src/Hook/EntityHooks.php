<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_ai\Field\AiSummaryFieldDefinition;

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
    if (!in_array($entity_type->id(), ['node', 'tpr_service'], TRUE)) {
      return [];
    }
    return AiSummaryFieldDefinition::create();
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('entity_form_display_presave')]
  public function entityFormDisplayPresave(EntityFormDisplayInterface $display): void {
    if ($display->id() !== 'tpr_service.tpr_service.default') {
      return;
    }

    // Add ai_summary field to TPR service form display.
    $this->addAiSummaryComponent($display, [
      'type' => 'ai_summary',
      'weight' => 7,
      'region' => 'content',
    ]);
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('entity_view_display_presave')]
  public function entityViewDisplayPresave(EntityViewDisplayInterface $display): void {
    if ($display->id() !== 'tpr_service.tpr_service.default') {
      return;
    }

    // Add ai_summary field to TPR service entity display.
    $this->addAiSummaryComponent($display, [
      'type' => 'text_default',
      'label' => 'hidden',
      'weight' => 4,
      'region' => 'content',
    ]);
  }

  /**
   * Adds the ai_summary field to a display.
   *
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $display
   *   The form or view display.
   * @param array $options
   *   The display options.
   */
  private function addAiSummaryComponent(EntityDisplayInterface $display, array $options): void {
    if (
      $display->isSyncing() ||
      $display->getComponent('ai_summary') ||
      isset($display->get('hidden')['ai_summary'])
    ) {
      return;
    }

    $display->setComponent('ai_summary', $options);
  }

}
