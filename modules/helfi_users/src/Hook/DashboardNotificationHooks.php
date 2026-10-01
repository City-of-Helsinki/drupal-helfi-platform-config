<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for user dashboard notifications.
 */
class DashboardNotificationHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_theme().
   *
   * @phpstan-return array<string, mixed>
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'helfi_dashboard_notifications' => [
        'variables' => [
          'notifications' => [],
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
  public function dashboardNotificationsField(array &$data): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }

      $table = $entity_type->getDataTable() ?? $entity_type->getBaseTable();

      if (!$table || !isset($data[$table])) {
        continue;
      }

      // Exposes the notifications field for all content entity types.
      $data[$table]['helfi_dashboard_notifications'] = [
        'title' => $this->t('Dashboard notifications'),
        'field' => [
          'title' => $this->t('Dashboard notifications'),
          'help' => $this->t('Notifications about the content.'),
          'id' => 'helfi_dashboard_notifications',
        ],
      ];
    }
  }

}
