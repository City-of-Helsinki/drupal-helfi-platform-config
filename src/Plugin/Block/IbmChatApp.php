<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a Watson chatbot block.
 */
#[Block(
  id: "ibm_chat_app",
  admin_label: new TranslatableMarkup("IBM Chat App"),
)]
class IbmChatApp extends BlockBase {

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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['hostname'] = $form_state->getValue('hostname');
    $this->configuration['engagementId'] = $form_state->getValue('engagementId');
    $this->configuration['tenantId'] = $form_state->getValue('tenantId');
    $this->configuration['assistantId'] = $form_state->getValue('assistantId');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    $library = ['helfi_platform_config/chat_enhancer'];

    $build = [];

    $config = $this->getConfiguration();

    $hostname = $config['hostname'];
    $engagementId = $config['engagementId'];
    $tenantId = $config['tenantId'];
    $assistantId = $config['assistantId'];

    $buttonSrc = sprintf('%s/get-widget-button?tenantId=%s&assistantId=%s&engagementId=%s', $hostname, $tenantId, $assistantId, $engagementId);

    $build['ibm_chat_app'] = [
      '#title' => $this->t('IBM Chat App'),
      '#attached' => [
        'library' => $library,
        'html_head' => [
          [
            [
              '#tag' => 'script',
              '#attributes' => [
                'async' => TRUE,
                'type' => 'text/javascript',
                'src' => $buttonSrc,
              ],
            ], 'chat_app_button',
          ],
        ],
      ],
    ];

    return $build;
  }

}
