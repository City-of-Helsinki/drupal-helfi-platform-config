<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Module hook implementations for modules.
 */
class ModuleHooks {

  use AutowireTrait;

  public function __construct(
    private readonly EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
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

    // Modules containing entities which needs color palette field.
    $moduleList = [
      'helfi_node_announcement',
      'helfi_node_landing_page',
      'helfi_node_news_item',
      'helfi_node_page',
      'helfi_tpr_config',
    ];

    if (!array_intersect($moduleList, $modules)) {
      return;
    }

    // Install color palette field to selected entities.
    $fields = [
      'color_palette',
      'hide_sidebar_navigation',
    ];
    $entityTypes = [
      'node',
      'tpr_unit',
      'tpr_service',
    ];

    foreach ($entityTypes as $entityType) {
      foreach ($fields as $field) {
        $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityType, $entityType);

        if (
          !empty($fieldDefinitions[$field]) &&
          $fieldDefinitions[$field] instanceof FieldStorageDefinitionInterface
        ) {
          $this->entityDefinitionUpdateManager->installFieldStorageDefinition(
            $field,
            $entityType,
            'hdbt_admin_tools',
            $fieldDefinitions[$field]
          );
        }
      }
    }
  }

}
