<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Module hook implementations for modules.
 */
class ModuleHooks {

  use AutowireTrait;

  public function __construct(
    private readonly EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled(array $modules, bool $is_syncing): void {
    if ($is_syncing) {
      return;
    }

    // The tpr_service field requires both modules to be installed.
    if (!array_intersect(['helfi_ai', 'helfi_tpr_config'], $modules)) {
      return;
    }

    $this->installAiSummaryField();
  }

  /**
   * Installs the ai_summary field and displays for tpr_service.
   */
  public function installAiSummaryField(): void {
    if (!$this->entityTypeManager->hasDefinition('tpr_service')) {
      return;
    }

    $definition = $this->entityFieldManager
      ->getFieldStorageDefinitions('tpr_service')['ai_summary'] ?? NULL;

    if (!$definition) {
      return;
    }

    $installed = $this->entityDefinitionUpdateManager
      ->getFieldStorageDefinition('ai_summary', 'tpr_service');

    if (!$installed) {
      $this->entityDefinitionUpdateManager
        ->installFieldStorageDefinition('ai_summary', 'tpr_service', 'helfi_ai', $definition);
    }
    elseif ($installed->getProvider() !== 'helfi_ai') {
      // Take over the field installed by helfi_tpr_config_update_11002().
      $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($definition);
    }

    // Save the displays so the presave hooks can place the field.
    foreach (['entity_form_display', 'entity_view_display'] as $displayType) {
      $display = $this->entityTypeManager->getStorage($displayType)
        ->load('tpr_service.tpr_service.default');

      $display?->save();
    }
  }

}
