<?php

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Chat Leijuke block.
 *
 * @Block(
 *  id = "chat_leijuke",
 *  admin_label = @Translation("Chat Leijuke"),
 * )
 */
class ChatLeijuke extends BlockBase {


  // TODO: block configiin käyttäjälle valinta mikä chat kilke, jonka perusteella ladataan ja triggeröidään sopiva js
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $library = ['helfi_platform_config/chat_leijuke'];
    $config = $this->getConfiguration();
    $build = [];

    // lataa valittujen kirjastojen datat yml / json tiedostosta

    $build['leijuke'] = [
      '#title' => $this->t('Chat Leijuke'),
      '#attached' => [
        'library' => $library,
        'drupalSettings' => [
          'leijuke_state' => [
            'chat_selection' => $config['chat_selection'] ?? '',
            // passaa library datat tänne?
            'libraries' => [],
          ],
        ],
      ]
    ];

    return $build;
  }

}
