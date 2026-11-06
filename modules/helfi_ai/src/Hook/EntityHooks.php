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
    $options = [
      'type' => 'ai_summary',
      'region' => 'content',
    ];

    match ($display->id()) {
      'node.page.default' => $this->addAiSummaryComponent($display, $options, 'field_lead_in'),
      'tpr_service.tpr_service.default' => $this->addAiSummaryComponent($display, $options + ['weight' => 7]),
      default => NULL,
    };
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('entity_view_display_presave')]
  public function entityViewDisplayPresave(EntityViewDisplayInterface $display): void {
    $options = [
      'type' => 'text_default',
      'label' => 'hidden',
      'region' => 'content',
    ];

    match ($display->id()) {
      'node.page.default' => $this->addAiSummaryComponent($display, $options, 'field_lead_in'),
      'tpr_service.tpr_service.default' => $this->addAiSummaryComponent($display, $options + ['weight' => 4]),
      default => NULL,
    };
  }

  /**
   * Adds the ai_summary field to a display.
   *
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $display
   *   The form or view display.
   * @param array $options
   *   The display options.
   * @param string|null $after
   *   The field to place the ai_summary field after.
   *
   * @phpstan-param array<string, mixed> $options
   */
  private function addAiSummaryComponent(EntityDisplayInterface $display, array $options, ?string $after = NULL): void {
    if ($display->isSyncing() || $display->getComponent('ai_summary')) {
      return;
    }

    $anchor = $after ? $display->getComponent($after) : NULL;
    if ($anchor) {
      $options['weight'] = $anchor['weight'] + 1;

      // Move the following components down.
      foreach ($display->getComponents() as $name => $component) {
        if ($component['weight'] >= $options['weight']) {
          $component['weight']++;
          $display->setComponent($name, $component);
        }
      }
    }

    $display->setComponent('ai_summary', $options);
  }

}
