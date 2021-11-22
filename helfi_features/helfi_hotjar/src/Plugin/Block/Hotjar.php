<?php

namespace Drupal\helfi_hotjar\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Hotjar' block.
 *
 * @Block(
 *  id = "hotjar",
 *  admin_label = @Translation("Hotjar"),
 * )
 */
class Hotjar extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $hotjarConfig = \Drupal::config('helfi_hotjar.settings');
    $build = [];

    $drupalSettings = [
      'hotjar' => [
        'id' => Html::escape($hotjarConfig->get('hjid')),
        'version' => Html::escape($hotjarConfig->get('hjsv')),
      ],
    ];

    $library = ['helfi_hotjar/hotjar'];

    $build['hotjar'] = [
      '#title' => t('Hotjar'),
      '#attached' => [
        'drupalSettings' => $drupalSettings,
        'library' => $library,
      ],
    ];

    return $build;
  }

}
