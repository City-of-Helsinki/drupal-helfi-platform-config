<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\ViewExecutable;

/**
 * Restricts the views bulk operations tool by permission.
 */
final readonly class BulkOperationsHooks {

  /**
   * The permission required to use views bulk operations.
   */
  public const string PERMISSION = 'allow views bulk operations';

  /**
   * The views bulk operations field plugin ID.
   */
  public const string FIELD_PLUGIN_ID = 'views_bulk_operations_bulk_form';

  public function __construct(
    private AccountInterface $currentUser,
  ) {}

  /**
   * Implements hook_views_pre_view().
   *
   * Removes views bulk operations fields from views for users without the
   * permission to use them.
   *
   * @phpstan-param array<mixed> $args
   */
  #[Hook('views_pre_view')]
  public function removeBulkOperationsField(ViewExecutable $view, string $display_id, array &$args): void {
    if ($this->currentUser->hasPermission(self::PERMISSION)) {
      return;
    }

    foreach ($view->getHandlers('field', $display_id) as $id => $field) {
      if (($field['plugin_id'] ?? NULL) === self::FIELD_PLUGIN_ID) {
        $view->removeHandler($display_id, 'field', $id);
      }
    }
  }

}
