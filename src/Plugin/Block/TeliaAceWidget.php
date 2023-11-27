<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Telia ACE chat widget block.
 *
 * @Block(
 *  id = "telia_ace_widget",
 *  admin_label = @Translation("Telia ACE Widget"),
 * )
 */
class TeliaAceWidget extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['script_url'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Script URL'),
      '#description' => $this->t('URL to the chat JS library without the domain, for example: /wds/instances/J5XKjqJt/ACEWebSDK.min.js'),
      '#default_value' => $config['script_url'] ?? '/wds/instances/J5XKjqJt/ACEWebSDK.min.js',
    ];

    $form['chat_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Chat Widget ID'),
      '#description' => $this->t('ID for the chat instance. Example format: //acewebsdk/example-chat-fin'),
      '#default_value' => $config['chat_id'] ?? '//acewebsdk/',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $formState) {
    $this->configuration['script_url'] = $formState->getValue('script_url');
    $this->configuration['chat_id'] = $formState->getValue('chat_id');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $library = ['helfi_platform_config/user_consent_functions'];

    $build = [];

    $config = $this->getConfiguration();
    $base_url = 'https://wds.ace.teliacompany.com';
    $script_url = $base_url . $config['script_url'];
    $chat_id = $config['chat_id'];

    $build['ibm_chat_app'] = [
      '#title' => $this->t('Telia ACE Widget'),
      '#markup' => '<a href="' . $chat_id . '"></a>',
      '#attached' => [
        'library' => $library,
        'html_head' => [
          [
            [
              '#tag' => 'script',
              '#attributes' => [
                'async' => TRUE,
                'type' => 'text/javascript',
                'src' => $script_url,
              ],
            ], 'telia_ace_script',
          ],
        ],
      ],
    ];

    return $build;
  }

}
