<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'SidebarContentBlock' block.
 */
#[Block(
  id: "sidebar_content_block",
  admin_label: new TranslatableMarkup("Sidebar content block"),
)]
class SidebarContentBlock extends ContentBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build['sidebar_content'] = [
      '#theme' => 'sidebar_content_block',
      '#title' => $this->t('Sidebar content block'),
    ];

    return $build;
  }

}
