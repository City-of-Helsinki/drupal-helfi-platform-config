<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Telia ace using a new api which supports strong authentication.
 */
#[Block(
  id: "telia_ace_authenticated_widget",
  admin_label: new TranslatableMarkup("Telia ACE Authenticated Widget"),
)]
class TeliaAceAuthenticatedWidget extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['chat_script_tag'] = [
      '#type' => 'textarea',
      '#required' => TRUE,
      '#title' => $this->t('The chat script tag'),
      '#description' => $this->t('This value is provided by the service provider. The value can be translated.'),
      '#default_value' => $config['chat_script_tag'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $scriptTag = $form_state->getValue('chat_script_tag');
    // Remove newlines from the text-area input.
    $this->configuration['chat_script_tag'] = trim(preg_replace('/\s\s+/', ' ', $scriptTag));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->getConfiguration();
    return [
      '#markup' => $config['chat_script_tag'],
      '#allowed_tags' => ['script'],
    ];
  }

}
