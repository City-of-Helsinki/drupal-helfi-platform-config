<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_users\FavoriteStorage;
use Drupal\helfi_users\FavoriteToggleBuilder;

/**
 * Favorite hooks.
 */
final class FavoriteHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
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

}
