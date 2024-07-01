<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
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
class TeliaAceWidget extends BlockBase {

  /**
   * URL for Telia ACE SDK script.
   */
  const SDK_URL = 'https://wds.ace.teliacompany.com/wds/instances/J5XKjqJt/ACEWebSDK.min.js';

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['chat_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Chat Widget ID'),
      '#description' => $this->t('ID for the chat instance, without the humany_ prefix. This value can be translated. Example format: example-chat-fin'),
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
  public function blockSubmit($form, FormStateInterface $formState) {
    $this->configuration['chat_id'] = $formState->getValue('chat_id');
    $this->configuration['chat_title'] = $formState->getValue('chat_title');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

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
