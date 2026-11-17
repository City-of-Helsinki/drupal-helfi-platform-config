<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a Watson chatbot block.
 */
#[Block(
  id: "ibm_chat_app",
  admin_label: new TranslatableMarkup("IBM Chat App"),
)]
class IbmChatApp extends ChatBlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    // hostname: Hostname of chat application.
    $form['hostname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chat Hostname'),
      '#default_value' => $config['hostname'] ?? '',
    ];

    // engagementId: will define how our chat application looks and behaves.
    $form['engagementId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chat Engagement Id'),
      '#default_value' => $config['engagementId'] ?? '',
    ];

    // tenantId: defines the environment to be used.
    $form['tenantId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chat Tenant Id'),
      '#default_value' => $config['tenantId'] ?? '',
    ];

    // assistantId: identifies the bot instance to be used.
    $form['assistantId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chat Assistant Id'),
      '#default_value' => $config['assistantId'] ?? '',
    ];

    return $this->chatButtonForm($form);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['hostname'] = $form_state->getValue('hostname');
    $this->configuration['engagementId'] = $form_state->getValue('engagementId');
    $this->configuration['tenantId'] = $form_state->getValue('tenantId');
    $this->configuration['assistantId'] = $form_state->getValue('assistantId');
    $this->configuration['chat_title'] = $form_state->getValue('chat_title');
    $this->configuration['chat_icon'] = $form_state->getValue('chat_icon');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->getConfiguration();

    $buttonSrc = sprintf(
      '%s/get-widget-button?tenantId=%s&assistantId=%s&engagementId=%s',
      $config['hostname'],
      $config['tenantId'],
      $config['assistantId'],
      $config['engagementId'],
    );

    return [
      'ibm_chat_app' => [
        '#attached' => [
          'library' => [
            'helfi_platform_config/chat_enhancer',
            'helfi_platform_config/chat_widget',
          ],
          'drupalSettings' => [
            'chatWidgetData' => [
              'scriptUrl' => $buttonSrc,
              'chatId' => Xss::filter($config['engagementId']),
              'provider' => 'ibm',
              ...$this->getChatButtonSettings(),
            ],
          ],
        ],
      ],
    ];
  }

}
