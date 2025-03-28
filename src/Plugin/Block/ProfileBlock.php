<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Profile Block.
 */
#[Block(
  id: "profile_block",
  admin_label: new TranslatableMarkup("Profile block"),
  category: new TranslatableMarkup("Profile"),
)]
final class ProfileBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Current User object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a new SwitchUserBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $renderArray = [
      '#theme' => 'profile_block',
      '#logged_in' => FALSE,
      '#url' => Url::fromRoute('user.login'),
    ];

    if ($this->currentUser->isAuthenticated()) {
      $renderArray = array_replace($renderArray, [
        '#logged_in' => TRUE,
        '#display_name' => explode(' ', $this->currentUser->getDisplayName())[0],
        '#full_name' => $this->currentUser->getDisplayName(),
        '#email' => $this->currentUser->getEmail(),
        '#url' => Url::fromRoute('user.logout'),
      ]);
    }

    return $renderArray;
  }

}
