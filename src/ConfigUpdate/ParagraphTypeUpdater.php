<?php

namespace Drupal\helfi_platform_config\ConfigUpdate;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\helfi_platform_config\DTO\ParagraphTypeCollection;

/**
 * Handles updates to paragraph type configurations.
 */
class ParagraphTypeUpdater {

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Updates paragraph target types based on module implementations.
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
      $field->save();
    }
  }

}
