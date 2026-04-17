<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigBase;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\DTO\ParagraphTypeCollection;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Implements entity hooks.
 */
class EntityHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly ConfigInstallerInterface $configInstaller,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('base_field_override_presave')]
  public function baseFieldOverridePresave(FieldConfigBase $field): void {
    if ($field->get('entity_type') !== 'paragraphs_library_item') {
      return;
    }
    $this->rebuildHandlerSettings($field);
  }

  /**
   * Implements hook_ENTITY_TYPE_presave().
   */
  #[Hook('field_config_presave')]
  public function fieldConfigPresave(FieldConfigBase $field): void {
    $this->rebuildHandlerSettings($field);
  }

  /**
   * Rebuilds the handler settings for a field.
   *
   * @param \Drupal\Core\Field\FieldConfigBase $field
   *   Field configuration.
   */
  protected function rebuildHandlerSettings(FieldConfigBase $field): void {
    if (
      $this->configInstaller->isSyncing() ||
      $field->isSyncing() ||
      $field->getType() !== 'entity_reference_revisions'
    ) {
      return;
    }

    $collection = [];
    $paragraphTypes = $this->moduleHandler->invokeAll('helfi_paragraph_types');

    foreach ($paragraphTypes as $type) {
      if (!$type instanceof ParagraphTypeCollection) {
        throw new \LogicException(
          sprintf('$type must be an instance of %s, %s given.', ParagraphTypeCollection::class, gettype($type))
        );
      }

      if (!ParagraphsType::load($type->paragraph)) {
        continue;
      }
      $collection[$type->entityType][$type->bundle][$type->field][] = $type;
    }
    if (!isset($collection[$field->getTargetEntityTypeId()][$field->getTargetBundle()][$field->getName()])) {
      return;
    }
    $paragraphTypes = $collection[$field->getTargetEntityTypeId()][$field->getTargetBundle()][$field->getName()];
    $handlerSettings = $field->getSetting('handler_settings');

    foreach ($paragraphTypes as $type) {
      $handlerSettings['target_bundles'][$type->paragraph] = $type->paragraph;
      $handlerSettings['target_bundles_drag_drop'][$type->paragraph] = [
        'weight' => $type->weight,
        'enabled' => TRUE,
      ];
    }
    $field->setSetting('handler_settings', $handlerSettings);
  }

}
