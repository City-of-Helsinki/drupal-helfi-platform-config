<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\RestResponse;

use Drupal\api_tools\Response\SuccessResponse;
use Webmozart\Assert\Assert;

/**
 * Defines a success response for news request.
 */
final class NewsResponse extends SuccessResponse {

  /**
   * The news entities.
   *
   * @var \Drupal\helfi_news_feed\RestResponse\News[]
   */
  public array $entities;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\helfi_news_feed\RestResponse\News[] $entities
   *   The entities.
   */
  public function __construct(array $entities) {
    Assert::allIsInstanceOf($entities, News::class);

    $this->entities = $entities;
  }

}
