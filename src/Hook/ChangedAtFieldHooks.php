<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\helfi_platform_config\DTO\ChangedAtFieldBundle;

/**
 * Provides a reusable 'changed_at' field for entity types that opt in.
 *
 * Submodules opt their entity type and bundle in by implementing
 * hook_helfi_changed_at_field_bundles(). Unlike the base 'changed' field,
 * 'changed_at' is only updated when content is edited through the UI, so it
 * is set from a form submit handler rather than hook_entity_presave(), which
 * would also fire for programmatic/API saves.
 *
 * @see \Drupal\helfi_platform_config\DTO\ChangedAtFieldBundle
 */
class ChangedAtFieldHooks {

  use AutowireTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Gets the entity type/bundle combinations that opted in.
   *
   * @return array<\Drupal\helfi_platform_config\DTO\ChangedAtFieldBundle>
   *   The configured entity type/bundle combinations.
   */
  private function getConfiguredBundles(): array {
    $bundles = [];

    foreach ($this->moduleHandler->invokeAll('helfi_changed_at_field_bundles') as $bundle) {
      if (!$bundle instanceof ChangedAtFieldBundle) {
        throw new \LogicException(
          sprintf('$bundle must be an instance of %s, %s given.', ChangedAtFieldBundle::class, get_debug_type($bundle))
        );
      }
      $bundles[] = $bundle;
    }
    return $bundles;
  }

  /**
   * Checks whether the given entity type/bundle opted in.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   *
   * @return bool
   *   TRUE if the entity type/bundle opted in.
   */
  private function isConfigured(string $entityTypeId, string $bundle): bool {
    foreach ($this->getConfiguredBundles() as $configured) {
      if ($configured->entityType === $entityTypeId && $configured->bundle === $bundle) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Builds the 'changed_at' bundle field definition.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\entity\BundleFieldDefinition
   *   The field definition.
   */
  private function fieldDefinition(string $entityTypeId, string $bundle): BundleFieldDefinition {
    return BundleFieldDefinition::create('timestamp')
      ->setName('changed_at')
      ->setLabel(new TranslatableMarkup('Content changed on'))
      ->setDescription(new TranslatableMarkup('Timestamp of when content was last changed via form.'))
      ->setTargetEntityTypeId($entityTypeId)
      ->setTargetBundle($bundle)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', FALSE);
  }

  /**
   * Implements hook_entity_bundle_field_info_alter().
   *
   * Adds a 'changed_at' field to entity types and bundles that opted in.
   *
   * @param array<string, mixed> $fields
   *   The bundle fields.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(array &$fields, EntityTypeInterface $entityType, string $bundle): void {
    if ($this->isConfigured($entityType->id(), $bundle)) {
      $fields['changed_at'] = $this->fieldDefinition($entityType->id(), $bundle);
    }
  }

  /**
   * Implements hook_entity_field_storage_info().
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return array<string, mixed>
   *   The field storage definitions.
   */
  #[Hook('entity_field_storage_info')]
  public function entityFieldStorageInfo(EntityTypeInterface $entityType): array {
    foreach ($this->getConfiguredBundles() as $configured) {
      if ($configured->entityType === $entityType->id()) {
        return ['changed_at' => $this->fieldDefinition($configured->entityType, $configured->bundle)];
      }
    }
    return [];
  }

  /**
   * Implements hook_form_alter().
   *
   * Adds a submit handler that updates 'changed_at' for entity forms whose
   * entity type/bundle opted in.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param string $formId
   *   The form id.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $formObject = $formState->getFormObject();

    if (!$formObject instanceof EntityFormInterface || !isset($form['actions']['submit'])) {
      return;
    }
    $entity = $formObject->getEntity();

    if (!$entity instanceof ContentEntityInterface || !$this->isConfigured($entity->getEntityTypeId(), $entity->bundle())) {
      return;
    }
    array_unshift($form['actions']['submit']['#submit'], [self::class, 'updateChangedAt']);
  }

  /**
   * Submit handler to update the 'changed_at' field.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public static function updateChangedAt(array $form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();

    if (!$formObject instanceof EntityFormInterface) {
      return;
    }
    $entity = $formObject->getEntity();

    if ($entity instanceof ContentEntityInterface && $entity->hasField('changed_at')) {
      $entity->set('changed_at', \Drupal::time()->getRequestTime());
    }
  }

}
