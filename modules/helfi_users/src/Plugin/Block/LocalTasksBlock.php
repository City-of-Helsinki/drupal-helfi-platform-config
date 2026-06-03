<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\Block;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\Plugin\Block\LocalTasksBlock as CoreLocalTasksBlock;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserInterface;

/**
 * Class to override core's LocalTaskBlock.
 */
class LocalTasksBlock extends CoreLocalTasksBlock {

  /**
   * Language-manager interface.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritDoc}
   *
   * @phpstan-return array<mixed>
   */
  public function build(): array {
    $build = parent::build();

    if (
      !isset($build['#primary']) ||
      !is_array($build['#primary'])
    ) {
      return $build;
    }

    // Return if the user is not the current user.
    if (
      !$this->routeMatch->getParameter('user') instanceof UserInterface ||
      $this->routeMatch->getParameter('user')->id() !== $this->currentUser->id()
    ) {
      return $build;
    }

    // Remove scheduled link from User page local tasks.
    $scheduleRoute = 'views_view:view.scheduler_scheduled_content.user_page';
    if (isset($build['#primary'][$scheduleRoute])) {
      unset($build['#primary'][$scheduleRoute]);
    }

    // Change the "View" to "My pages" when viewing the user entity routes.
    if (isset($build['#primary']['entity.user.canonical'])) {
      $adminLanguage = $this->currentUser->getPreferredAdminLangcode();
      $build['#primary']['entity.user.canonical']['#link']['title'] = $this->t('My pages', options: [
        'context' => 'Dashboard',
        'langcode' => $adminLanguage,
      ]);
      $build['#cache']['contexts'][] = 'user';
    }
    return $build;
  }

}
