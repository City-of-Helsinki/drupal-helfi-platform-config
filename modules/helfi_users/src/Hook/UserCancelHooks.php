<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Hook implementations for cancelling user accounts.
 */
final class UserCancelHooks {

  use AutowireTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {
  }

  /**
   * Implements hook_user_cancel().
   *
   * We have encountered crashes/timeout issues with reassign batch api
   * implementation from node module. This Optimizes hook_user_cancel by
   * reassigning nodes with direct database query.
   *
   * Runs first, before node module's user_cancel hook, which causes issues
   * when mass reassigning node revisions.
   */
  #[Hook('user_cancel', order: Order::First)]
  public function userCancel($edit, UserInterface $account, $method): void {
    // Reassign nodes for the old account.
    if ($method === 'user_cancel_reassign') {
      $this->reassignNodes($account, User::load(1));
    }
  }

  /**
   * Reassigns all node revisions from $source to $target.
   *
   * Prevents crashes and timeouts when revisions are handled by
   * node_mass_update.
   *
   * @param \Drupal\Core\Session\AccountInterface $source
   *   Source user.
   * @param \Drupal\Core\Session\AccountInterface $target
   *   Target user.
   */
  private function reassignNodes(AccountInterface $source, AccountInterface $target): void {
    $tables = [
      'node_field_data' => 'uid',
      'node_field_revision' => 'uid',
      'node_revision' => 'revision_uid',
    ];

    foreach ($tables as $table => $uid_field) {
      $matches = $this->database->select($table)
        ->condition($uid_field, $source->id())
        ->countQuery()
        ->execute()
        ->fetchField();

      if ((int) $matches < 1) {
        continue;
      }

      $this->database->update($table)
        ->fields([$uid_field => $target->id()])
        ->condition($uid_field, $source->id())
        ->execute();

      $this->loggerFactory->get('helfi_users')->notice(new FormattableMarkup('Set @count rows from @table to @target from @source', [
        '@count' => $matches,
        '@table' => $table,
        '@target' => $target->id(),
        '@source' => $source->id(),
      ]));
    }

    // Invalidate cache for these nodes.
    $affected_node_ids = $this->database->select('node_field_data')
      ->fields('node_field_data', ['nid'])
      ->condition('uid', $target->id())
      ->execute()
      ->fetchCol();

    if (!empty($affected_node_ids)) {
      $this->entityTypeManager->getStorage('node')->resetCache($affected_node_ids);
    }
  }

}
