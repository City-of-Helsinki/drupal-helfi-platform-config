<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_users\FavoriteStorage;
use Drupal\helfi_users\FavoriteToggleBuilder;
use Drupal\user\UserInterface;

/**
 * Favorite hooks.
 */
final class FavoriteHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
    private readonly FavoriteStorage $favoriteStorage,
  ) {}

  /**
   * Implements hook_theme().
   *
   * @return array<string, mixed>
   *   The theme definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'helfi_favorite_toggle' => [
        'variables' => [
          'attributes' => [],
          'is_favorite' => FALSE,
          'show_label' => FALSE,
        ],
      ],
    ];
  }

  /**
   * Implements hook_views_data_alter().
   *
   * @phpstan-param array<string, mixed> $data
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    // Add the favorite field to each installed favorite entity type.
    foreach (FavoriteStorage::ENTITY_TYPES as $entityTypeId) {
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId, FALSE);
      if (!$entityType) {
        continue;
      }

      $table = $entityType->getDataTable() ?? $entityType->getBaseTable();
      $data[$table]['helfi_favorite'] = [
        'title' => $this->t('Favorite'),
        'help' => $this->t('Favorite toggle for the current user.'),
        'field' => [
          'id' => 'helfi_favorite',
          'entity_type' => $entityTypeId,
          'id_field' => $entityType->getKey('id'),
          'langcode_field' => $entityType->getKey('langcode'),
          'click sortable' => TRUE,
        ],
      ];
    }
  }

  /**
   * Implements hook_preprocess_page().
   *
   * @phpstan-param array<string, mixed> $variables
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    // Show the favorite toggle only on the edit pages.
    $entityType = NULL;
    foreach (FavoriteStorage::ENTITY_TYPES as $type) {
      if ($this->routeMatch->getRouteName() === "entity.$type.edit_form") {
        $entityType = $type;
        break;
      }
    }

    if (!$entityType) {
      return;
    }

    $entity = $this->routeMatch->getParameter($entityType);
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    // Set the favorite_toggle variable to the page template.
    $variables['favorite_toggle'] = [
      '#lazy_builder' => [
        FavoriteToggleBuilder::class . ':lazyBuild',
        [
          $entityType,
          (string) $entity->id(),
          $entity->language()->getId(),
          'label',
        ],
      ],
      '#create_placeholder' => TRUE,
    ];
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (in_array($entity->getEntityTypeId(), FavoriteStorage::ENTITY_TYPES, TRUE)) {
      $this->favoriteStorage->deleteByEntity($entity);
    }
  }

  /**
   * Implements hook_entity_translation_delete().
   */
  #[Hook('entity_translation_delete')]
  public function entityTranslationDelete(EntityInterface $translation): void {
    if (in_array($translation->getEntityTypeId(), FavoriteStorage::ENTITY_TYPES, TRUE)) {
      $this->favoriteStorage->deleteByEntity($translation, $translation->language()->getId());
    }
  }

  /**
   * Implements hook_user_delete().
   */
  #[Hook('user_delete')]
  public function userDelete(UserInterface $account): void {
    $this->favoriteStorage->deleteByUser($account);
  }

}
