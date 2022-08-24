<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides a Chat Leijuke block.
 *
 * @Block(
 *  id = "chat_leijuke",
 *  admin_label = @Translation("Chat Leijuke"),
 * )
 */
class ChatLeijuke extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['chat_selection'] = [
      '#type' => 'select',
      '#title' => $this->t('Chat/bot provider'),
      '#description' => $this->t('Choose the approriate chat/bot provider?'),
      '#default_value' => $config['chat_selection'] ?? '',
      '#options' => [
        'kuura_health_chat' => 'kuura_health_chat',
        'smartti_chatbot' => 'smartti_chatbot',
        'genesys_chat' => 'genesys_chat',
        'genesys_suunte' => 'genesys_suunte',
        'genesys_neuvonta' => 'genesys_neuvonta',
        'watson_chatbot' => 'watson_chatbot',
      ],
    ];

    $form['chat_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chat title'),
      '#default_value' => $config['chat_title'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $formState) {
    $this->configuration['chat_selection'] = $formState->getValue('chat_selection');
    $this->configuration['chat_title'] = $formState->getValue('chat_title');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $library = ['helfi_platform_config/chat_leijuke'];
    $config = $this->getConfiguration();
    $build = [];
    $chatLibrary = [];
    $modulePath = \Drupal::service('extension.list.module')->getPath('helfi_platform_config');

    $librariesYml = Yaml::parseFile($modulePath . '/helfi_platform_config.libraries.yml');

    foreach ($librariesYml as $k => $lib) {
      if ($k === $config['chat_selection']) {
        foreach ($lib['js'] as $key => $value) {
          $js = [
            'url' => $key,
            'ext' => $value['type'] ?? FALSE,
            'onload' => $value['attributes']['onload'] ?? FALSE,
            'async' => $value['attributes']['async'] ?? FALSE,
            'data_container_id' => $value['attributes']['data-container-id'] ?? FALSE
          ];

          $chatLibrary['js'][] = $js;
        }

        foreach ($lib['css']['theme'] as $key => $value) {
          $css = [
            'url' => $key,
            'ext' => $value['type'] ?? FALSE
          ];

          $chatLibrary['css'][] = $css;
        }
      }
    }

    // We only build it if it makes sense.
    if ($config['chat_selection']) {
      $build['leijuke'] = [
        '#title' => $this->t('Chat Leijuke'),
        '#attached' => [
          'library' => $library,
          'drupalSettings' => [
            'leijuke_data' => [
              $config['chat_selection'] => [
                'name' => $config['chat_selection'],
                'libraries' => $chatLibrary,
                'modulepath' => $modulePath,
                'title' => $config['chat_title'] ?? 'Chat',
              ],
            ],
          ],
        ]
      ];
    }

    return $build;
  }
}
