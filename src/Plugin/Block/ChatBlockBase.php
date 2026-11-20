<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for chat widget blocks.
 */
abstract class ChatBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  use AutowiredInstanceTrait;

  /**
   * The default chat button icon.
   */
  protected const DEFAULT_CHAT_ICON = 'speechbubble-text';

  /**
   * Routes that render an error page.
   */
  private const ERROR_ROUTES = [
    'system.401',
    'system.403',
    'system.404',
    'system.4xx',
  ];

  /**
   * Constructs a new instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   *
   * @phpstan-param array<mixed> $configuration
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return static::createInstanceAutowired($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Get the chat button icon options.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Returns the icon labels keyed by the icon name.
   */
  protected function getIconOptions(): array {
    return [
      'speechbubble-text' => $this->t('Chat with a person'),
      'customer-bot-positive' => $this->t('Chat with a chatbot'),
    ];
  }

  /**
   * Add the chat button label and icon fields to the form.
   *
   * @param array $form
   *   The block form.
   *
   * @return array
   *   Returns the block form with the chat button fields.
   *
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return array<string, mixed>
   */
  protected function chatButtonForm(array $form): array {
    $config = $this->getConfiguration();

    $form['chat_title'] = [
      '#type' => 'textfield',
      '#required' => FALSE,
      '#title' => $this->t('Chat button label'),
      '#description' => $this->t('Label for placeholder button. Defaults to "Open chat".'),
      '#default_value' => $config['chat_title'] ?? '',
    ];

    // Show each icon next to its label.
    $icon_options = [];
    foreach ($this->getIconOptions() as $icon => $label) {
      $icon_options[$icon] = new FormattableMarkup('<span class="hel-icon hel-icon--@icon" aria-hidden="true"></span> @label', [
        '@icon' => $icon,
        '@label' => $label,
      ]);
    }

    $form['chat_icon'] = [
      '#type' => 'radios',
      '#required' => TRUE,
      '#title' => $this->t('Chat button icon'),
      '#options' => $icon_options,
      '#default_value' => $config['chat_icon'] ?? self::DEFAULT_CHAT_ICON,
      '#attached' => ['library' => ['hdbt/hdbt-icons']],
    ];

    return $form;
  }

  /**
   * Get the chat button label and icon for the chat widget settings.
   *
   * @return array<string, string>
   *   Returns the chat title and icon.
   */
  protected function getChatButtonSettings(): array {
    $config = $this->getConfiguration();

    return [
      'chatTitle' => !empty($config['chat_title']) ? Xss::filter($config['chat_title']) : (string) $this->t('Open chat', [], ['context' => 'Chat widget']),
      'chatIcon' => array_key_exists($config['chat_icon'] ?? '', $this->getIconOptions())
        ? $config['chat_icon']
        : self::DEFAULT_CHAT_ICON,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    // Hide the block on error routes.
    $access = in_array($this->routeMatch->getRouteName(), self::ERROR_ROUTES, TRUE)
      ? AccessResult::forbidden()
      : AccessResult::allowed();

    return $access->addCacheContexts(['route.name']);
  }

}
