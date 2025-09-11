<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Telia ACE chat widget block.
 */
#[Block(
  id: "telia_ace_widget",
  admin_label: new TranslatableMarkup("Telia ACE Widget"),
)]
final class TeliaAceWidget extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * URL for Telia ACE SDK script.
   */
  const SDK_URL = 'https://wds.ace.teliacompany.com/wds/instances/J5XKjqJt/ACEWebSDK.min.js';

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    assert($container->get('module_handler') instanceof ModuleHandlerInterface);
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['chat_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Chat Widget ID'),
      '#description' => $this->t('This value is provided by the service provider. The value can be translated. Example format: example-chat-fin'),
      '#default_value' => $config['chat_id'] ?? '',
    ];

    $form['chat_title'] = [
      '#type' => 'textfield',
      '#required' => FALSE,
      '#title' => $this->t('Chat button label'),
      '#description' => $this->t('Label for placeholder button. Defaults to "Chat".'),
      '#default_value' => $config['chat_title'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['chat_id'] = $form_state->getValue('chat_id');
    $this->configuration['chat_title'] = $form_state->getValue('chat_title');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    $build = [];

    $config = $this->getConfiguration();
    $chat_id = 'humany_' . $config['chat_id'];
    $attached = [
      'library' => ['helfi_platform_config/telia_ace_widget_loadjs'],
      'drupalSettings' => [
        'telia_ace_data' => [
          'script_url' => self::SDK_URL,
          'script_sri' => NULL,
          'chat_id' => Xss::filter($config['chat_id']),
          'chat_title' => $config['chat_title'] ? Xss::filter($config['chat_title']) : 'Chat',
        ],
      ],
    ];
    if ($this->moduleHandler->moduleExists('csp')) {
      $attached['csp'] = [
        'connect-src' => [
          'https://hel.humany.net',
          'https://wds.ace.teliacompany.com',
          'https://chat.ace.teliacompany.net',
          'https://api.ace.teliacompany.net',
        ],
        'font-src' => [
          'https://hel.humany.net',
          'https://ace-knowledge-cdn.teliacompany.net',
          'https://makasiini.hel.ninja',
        ],
        'frame-src' => [
          'https://wds.ace.teliacompany.com',
        ],
        'script-src' => [
          'https://wds.ace.teliacompany.com',
        ],
        'style-src' => [
          'https://hel.humany.net',
          'https://wds.ace.teliacompany.com',
        ],
      ];
    }

    $build['telia_chat_widget'] = [
      'button' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'role' => 'region',
          'aria-label' => 'chat',
          'id' => $chat_id,
          'class' => [
            'hidden',
          ],
        ],
      ],
      '#attached' => $attached,
    ];

    return $build;
  }

}
