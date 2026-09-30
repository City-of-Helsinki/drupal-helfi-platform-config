<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Block for rendering the news archive react app.
 */
#[Block(
  id: 'block__news_archive_application',
  admin_label: new TranslatableMarkup('News archive'),
  category: new TranslatableMarkup('HELfi News Archive'),
)]
class NewsArchiveBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#markup' => '<div id="helfi-etusivu-news-search" class="block--news-archive"></div>',
      '#attached' => [
        'library' => [
          'helfi_news_archive/news-archive',
        ],
      ],
    ];
    return $build;
  }

}
