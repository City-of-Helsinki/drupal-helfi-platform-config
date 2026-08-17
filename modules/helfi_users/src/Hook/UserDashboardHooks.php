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
   * Extra field definitions for the user dashboard display.
   *
   * @phpstan-return array<string, array{info: array{label: \Drupal\Core\StringTranslation\TranslatableMarkup, description: \Drupal\Core\StringTranslation\TranslatableMarkup}, view: array{name: string, display: string}}>
   */
  private function userContentExtraFields(): array {
    return [
      'user_content' => [
        'info' => [
          'label' => $this->t('User content'),
          'description' => $this->t('Lists all content authored by this user.'),
        ],
        'view' => [
          'name' => 'dashboard_your_content',
          'display' => 'your_content_block',
        ],
      ],
      'user_tpr_units' => [
        'info' => [
          'label' => $this->t('User TPR units'),
          'description' => $this->t('Lists all TPR units edited by this user.'),
        ],
        'view' => [
          'name' => 'tpr_unit_list',
          'display' => 'your_content_block',
        ],
      ],
      'user_tpr_services' => [
        'info' => [
          'label' => $this->t('User TPR services'),
          'description' => $this->t('Lists all TPR services edited by this user.'),
        ],
        'view' => [
          'name' => 'tpr_service_list',
          'display' => 'your_content_block',
        ],
      ],
      'user_tpr_errand_services' => [
        'info' => [
          'label' => $this->t('User TPR errand services'),
          'description' => $this->t('Lists all TPR errand services edited by this user.'),
        ],
        'view' => [
          'name' => 'tpr_errand_service_list',
          'display' => 'your_content_block',
        ],
      ],
      'tpr_service_channels' => [
        'info' => [
          'label' => $this->t('User TPR service channels'),
          'description' => $this->t('Lists all TPR errand services edited by this user.'),
        ],
        'view' => [
          'name' => 'tpr_service_channel_list',
          'display' => 'your_content_block',
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
    // Create an extra fields to inject the dashboard views for the user
    // display.
    $extra = [];
    foreach ($this->userContentExtraFields() as $name => $field) {
      $extra['user']['user']['display'][$name] = $field['info'] + [
        'weight' => 10,
        'visible' => TRUE,
      ];
    }
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
    foreach ($this->userContentExtraFields() as $name => $field) {
      if (!$display->getComponent($name)) {
        return;
      }
      $view = Views::getView($field['view']['name']);
      if (!$view || !$view->access($field['view']['display'])) {
        return;
      }
      $build[$name] = [
        '#type' => 'view',
        '#name' => $field['view']['name'],
        '#display_id' => $field['view']['display'],
        '#arguments' => [$account->id()],
      ];
    }
  }

}
