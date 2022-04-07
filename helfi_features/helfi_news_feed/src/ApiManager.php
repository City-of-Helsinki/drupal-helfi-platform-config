<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\paragraphs\ParagraphInterface;
use GuzzleHttp\ClientInterface;

final class ApiManager {

  private const BASE_PATH = 'jsonapi/node/news';
  private string $environment;

  public function __construct(
    private ClientInterface $client,
    private EnvironmentResolver $environmentResolver,
    private LanguageManagerInterface $languageManager,
    ConfigFactoryInterface $configFactory
  ) {
    $this->environment = $configFactory->get('helfi_news_feed.settings')
      ->get('source_environment') ?: 'prod';
  }

  private function getBaseUrl() : string {
    $language = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    $url = $this->environmentResolver
      ->getEnvironment(Project::ETUSIVU, $this->environment)
      ->getBaseUrl($language);

    return sprintf('%s/%s', $url, self::BASE_PATH);
  }

  private function buildQuery(ParagraphInterface $paragraph) : array {
    try {
      $response = $this->client->request('GET', $this->getBaseUrl());

      return json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (\Exception $e) {
    }
    return [];
  }

  public function toRenderArray(ParagraphInterface $paragraph) : array {
    $data = $this->buildQuery($paragraph);

    return [
      '#type' => 'markup',
      '#markup' => '123',
      '#cache' => ['max-age' => 0],
    ];
  }

}
