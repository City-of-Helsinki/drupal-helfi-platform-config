<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed;

use Drupal\api_tools\Rest\RequestFactory;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_news_feed\Entity\NewsFeedParagraph;
use Drupal\helfi_news_feed\Plugin\RestApiRequest\NewsRequest;

final class ApiManager {

  private NewsRequest $request;

  public function __construct(
    RequestFactory $requestFactory,
    private LanguageManagerInterface $languageManager,
  ) {
    $this->request = $requestFactory->create('helfi_news');
  }

  public function toRenderArray(NewsFeedParagraph $paragraph) : array {
    $language = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();
    $data = $this->request->list($paragraph, $language);

    return [
      '#type' => 'markup',
      '#markup' => '123',
      '#cache' => ['max-age' => 0],
    ];
  }

}
