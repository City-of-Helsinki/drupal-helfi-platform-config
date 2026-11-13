<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an outdated browser notification block.
 *
 * The notification is rendered hidden for everyone, and the
 * outdated_browser library shows it only if the user's browser is outdated.
 *
 * @see assets/js/outdatedBrowser.js
 * @see tools/build-outdated-browser.mjs
 */
#[Block(
  id: "outdated_browser",
  admin_label: new TranslatableMarkup("Outdated browser notification"),
)]
final class OutdatedBrowserBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $options = ['context' => 'Outdated browser'];

    return [
      '#theme' => 'outdated_browser',
      '#title' => $this->t('Your browser is out of date', options: $options),
      '#body' => $this->t('The browser you are using is outdated and might pose a security risk when you visit other websites. Update your browser to the latest available version.', options: $options),
      '#type_label' => $this->t('Attention', options: $options),
      '#close_label' => $this->t('Close', options: $options),
      '#attached' => [
        'library' => ['helfi_platform_config/outdated_browser'],
      ],
    ];
  }

}
