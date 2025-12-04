<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdater;
use Drupal\helfi_platform_config\DTO\ParagraphTypeCollection;

/**
 * Module hook implementations for modules.
 */
class ModuleHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigUpdater $configUpdater,
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

    if ($this->moduleHandler->moduleExists('locale')) {
      locale_system_set_config_langcodes();
    }

    foreach ($modules as $module) {
      $permissions = $this->moduleHandler->invoke($module, 'platform_config_grant_permissions');
      $this->configUpdater->updatePermissions($permissions ?? []);
    }

    $this->updateParagraphTargetTypes();
  }

  /**
   * Invokes all helfi_paragraph_types hooks and updates field configurations.
   */
  public function updateParagraphTargetTypes(): void {
    $paragraphTypes = $this->moduleHandler->invokeAll('helfi_paragraph_types');

    foreach ($paragraphTypes as $type) {
      if (!$type instanceof ParagraphTypeCollection) {
        throw new \LogicException(
          sprintf('$type must be an instance of %s, %s given.', ParagraphTypeCollection::class, gettype($type))
        );
      }
      if (!$definitions = $this->entityFieldManager->getFieldDefinitions($type->entityType, $type->bundle)) {
        continue;
      }
      if (!isset($definitions[$type->field])) {
        continue;
      }
      $field = $definitions[$type->field];

      // Base fields use BaseFieldDefinition instances while configurable fields
      // use FieldConfig instances. Save the BaseFieldOverride to trigger
      // re-build of target_bundles.
      if ($field instanceof BaseFieldDefinition) {
        $field = $field->getConfig($type->bundle);
      }

      // Save the field to trigger re-build of target_bundles.
      // @see helfi_platform_config_field_config_presave().
      // @see helfi_platform_config_base_field_override_presave().
      $field->save();
    }
  }

}
