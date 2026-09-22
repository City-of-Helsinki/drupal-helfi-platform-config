<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigBase;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\helfi_platform_config\DTO\ParagraphTypeCollection;
use Drupal\helfi_platform_config\Entity\ExternalEntity\MultisiteContent;
use Drupal\helfi_platform_config\MultisiteContentId;
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
   *
   * Runs first, before field module's own presave, so the rebuilt handler
   * settings are in place before field module acts on them.
   */
  #[Hook('field_config_presave', order: Order::First)]
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

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    if (isset($bundles['helfi_multisite_content']['helfi_multisite_content'])) {
      $bundles['helfi_multisite_content']['helfi_multisite_content']['class'] = MultisiteContent::class;
    }
  }

  /**
   * Implements hook_entity_bundle_field_info_alter().
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, $bundle): void {
    if ($entity_type->id() !== 'helfi_multisite_content') {
      return;
    }

    $additionalFields = [
      'entity_url' => new TranslatableMarkup('Entity URL'),
      'language_code' => new TranslatableMarkup('Language code'),
    ];
    foreach ($additionalFields as $field_name => $field_label) {
      $fields[$field_name] = BundleFieldDefinition::create('string')
        ->setName($field_name)
        ->setLabel($field_label)
        ->setDisplayConfigurable('view', TRUE)
        ->setDisplayConfigurable('form', TRUE);
    }
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    if (!isset($entity_types[MultisiteContentId::ENTITY_TYPE_ID])) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    $entity_type = $entity_types[MultisiteContentId::ENTITY_TYPE_ID];
    $canonical = MultisiteContentId::canonicalPathTemplate();
    $entity_type->setLinkTemplate('canonical', $canonical);
    foreach (['edit-form' => '/edit', 'delete-form' => '/delete'] as $rel => $suffix) {
      if ($entity_type->hasLinkTemplate($rel)) {
        $entity_type->setLinkTemplate($rel, MultisiteContentId::canonicalPathTemplate($suffix));
      }
    }
    if ($entity_type->hasLinkTemplate('collection')) {
      $entity_type->setLinkTemplate('collection', '/' . MultisiteContentId::PATH_PREFIX);
    }
  }

}
