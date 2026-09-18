<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Extra field definitions for the user dashboard display.
   *
   * @phpstan-return array<string, array{
   *   info: array{
   *     label: \Drupal\Core\StringTranslation\TranslatableMarkup,
   *     description: \Drupal\Core\StringTranslation\TranslatableMarkup,
   *   },
   *   view: array{name: string, display: string},
   * }>
   */
  private function userContentExtraFields(): array {
    $definitions = [
      'user_content' => [
        $this->t('User content'),
        $this->t('Lists all content authored by this user.'),
        'dashboard_your_content',
      ],
      'user_tpr_units' => [
        $this->t('User TPR units'),
        $this->t('Lists all TPR units edited by this user.'),
        'tpr_unit_list',
      ],
      'user_tpr_services' => [
        $this->t('User TPR services'),
        $this->t('Lists all TPR services edited by this user.'),
        'tpr_service_list',
      ],
      'user_tpr_errand_services' => [
        $this->t('User TPR errand services'),
        $this->t('Lists all TPR errand services edited by this user.'),
        'tpr_errand_service_list',
      ],
      'tpr_service_channels' => [
        $this->t('User TPR service channels'),
        $this->t('Lists all TPR service channels edited by this user.'),
        'tpr_service_channel_list',
      ],
    ];

    $fields = [];
    foreach ($definitions as $name => [$label, $description, $view_name]) {
      $fields[$name] = [
        'info' => [
          'label' => $label,
          'description' => $description,
        ],
        'view' => [
          'name' => $view_name,
          'display' => 'your_content_block',
        ],
      ];
    }
    return $fields;
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
        continue;
      }
      $view = Views::getView($field['view']['name']);
      if (!$view || !$view->access($field['view']['display'])) {
        continue;
      }
      $build[$name] = [
        '#type' => 'view',
        '#name' => $field['view']['name'],
        '#display_id' => $field['view']['display'],
        '#arguments' => [$account->id()],
      ];
    }
  }

  /**
   * Implements hook_field_group_pre_render().
   *
   * @phpstan-param array<string, mixed> $element
   * @phpstan-param \stdClass $group
   */
  #[Hook('field_group_pre_render')]
  public function fieldGroupPreRender(array &$element, &$group): void {
    // Hide user dashboard TPR content tab, if TPR integration isn't enabled.
    if (
      $group->group_name === 'group_my_tpr_content'
      && !$this->moduleHandler->moduleExists('helfi_tpr')
    ) {
      $element['#access'] = FALSE;
    }
  }

}
