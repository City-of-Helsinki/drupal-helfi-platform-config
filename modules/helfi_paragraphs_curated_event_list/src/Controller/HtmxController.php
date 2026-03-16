<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_curated_event_list\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\helfi_paragraphs_curated_event_list\Entity\LazyViewBuilder;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Controller for Curated event list HTMX response.
 *
 * @see \Drupal\helfi_paragraphs_curated_event_list\Entity\LazyViewBuilder
 * @see \helfi_paragraphs_curated_event_list_paragraph_view()
 */
final class HtmxController extends ControllerBase {

  public function __construct(
    private LazyViewBuilder $lazyViewBuilder,
  ) {
  }

  public function content(Paragraph $paragraph): array {
    return $this->lazyViewBuilder->build($paragraph)
  }

}
