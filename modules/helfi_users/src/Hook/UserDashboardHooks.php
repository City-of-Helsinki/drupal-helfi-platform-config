<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Drupal\views\Views;

/**
 * Hook implementations for helfi_users module.
 */
class UserDashboardHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_views_data_alter().
   *
   * @phpstan-param array<string, mixed> $data
   */
  #[Hook('views_data_alter')]
  public function nodeAuthorshipFilter(array &$data): void {
    $data['node_field_data']['helfi_node_authorship'] = [
      'title' => $this->t('Node authorship (current user)'),
      'filter' => [
        'title' => $this->t('Node authorship (current user)'),
        'help' => $this->t('Filter nodes by whether the current user authored or last edited them.'),
        'id' => 'helfi_node_authorship',
      ],
    ];
  }

  /**
   * Implements hook_entity_extra_field_info().
   *
   * @phpstan-return array<string, mixed>
   */
  #[Hook('entity_extra_field_info')]
  public function userContentExtraFieldInfo(): array {
    // Create an extra field that will be used to inject dashboard view for the user display.
    $extra = [];
    $extra['user']['user']['display']['user_content'] = [
      'label' => $this->t('User content'),
      'description' => $this->t('Lists all content authored by this user.'),
      'weight' => 10,
      'visible' => TRUE,
    ];
    return $extra;
  }

  /**
   * Implements hook_user_view().
   *
   * @phpstan-param array<string, mixed> $build
   */
  #[Hook('user_view')]
  public function injectDashboardView(array &$build, UserInterface $account, EntityViewDisplayInterface $display): void {
    if ($this->currentUser->id() !== $account->id()) {
      return;
    }
    if (!$display->getComponent('user_content')) {
      return;
    }
    $view = Views::getView('dashboard_your_content');
    if (!$view || !$view->access('your_content_block')) {
      return;
    }
    $build['user_content'] = [
      '#type' => 'view',
      '#name' => 'dashboard_your_content',
      '#display_id' => 'your_content_block',
      '#arguments' => [$account->id()],
    ];
  }
}
