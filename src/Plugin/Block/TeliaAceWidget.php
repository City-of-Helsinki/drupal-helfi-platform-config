<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a Telia ACE chat widget block.
 */
#[Block(
  id: "telia_ace_widget",
  admin_label: new TranslatableMarkup("Telia ACE Widget"),
)]
class TeliaAceWidget extends ChatBlockBase {

  /**
   * URL for Telia ACE SDK script.
   */
  const SDK_URL = 'https://wds.ace.teliacompany.com/wds/instances/J5XKjqJt/ACEWebSDK.min.js';

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

    return $this->chatButtonForm($form);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['chat_id'] = $form_state->getValue('chat_id');
    $this->configuration['chat_title'] = $form_state->getValue('chat_title');
    $this->configuration['chat_icon'] = $form_state->getValue('chat_icon');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    $build = [];

    $config = $this->getConfiguration();
    $chat_id = 'humany_' . $config['chat_id'];
    $attached = [
      'library' => ['helfi_platform_config/chat_widget'],
      'drupalSettings' => [
        'chatWidgetData' => [
          'scriptUrl' => self::SDK_URL,
          'scriptSri' => NULL,
          'chatId' => Xss::filter($config['chat_id']),
          ...$this->getChatButtonSettings(),
        ],
      ],
    ];

    $build['chat_widget'] = [
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
