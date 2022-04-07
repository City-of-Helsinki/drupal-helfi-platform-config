<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\RestResponse;

use League\Uri\Contracts\UriInterface;

/**
 * A value object for news item.
 */
final class News {

  /**
   * Constructs a new instance.
   *
   * @param string $id
   *   The ID.
   * @param string $title
   *   The title.
   * @param string $language
   *   The language.
   * @param string $created
   *   The created date.
   * @param string $changed
   *   The changed date.
   */
  public function __construct(
    public string $id,
    public string $title,
    public string $language,
    public string $created,
    public string $changed,
    public UriInterface $url,
  ) {
  }

}
