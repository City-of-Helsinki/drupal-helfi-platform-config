<?php

declare(strict_types = 1);

namespace Drupal\helfi_news_feed\Plugin\RestApiRequest;

use Drupal\api_tools\Request\Request;
use Drupal\api_tools\Rest\ApiRequestBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_news_feed\Entity\NewsFeedParagraph;
use Drupal\helfi_news_feed\RestResponse\News;
use Drupal\helfi_news_feed\RestResponse\NewsResponse;
use League\Uri\Contracts\UriInterface;
use League\Uri\Uri;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the 'News' request plugin.
 *
 * @RestApiRequest(
 *   id = "helfi_news",
 * )
 */
final class NewsRequest extends ApiRequestBase implements ContainerFactoryPluginInterface {

  /**
   * The environment resolver.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentResolver
   */
  private EnvironmentResolver $environmentResolver;

  /**
   * The active environment.
   *
   * @var string
   */
  private string $environment = 'prod';

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->environmentResolver = $container->get('helfi_api_base.environment_resolver');
    $environment = $container->get('config.factory')
      ->get('helfi_news_feed.settings')
      ->get('source_environment');

    if ($environment) {
      $instance->environment = $environment;
    }
    return $instance;
  }

  /**
   * Gets the base URI for given language.
   *
   * @param string $language
   *   The language.
   *
   * @return string
   *   The instance URL.
   */
  private function getBaseUri(string $language) : string {
    return $this->environmentResolver
      ->getEnvironment(Project::ETUSIVU, $this->environment)
      ->getBaseUrl($language);
  }

  /**
   * Gets the API uri for given language and params.
   *
   * @param string $language
   *   The language.
   * @param array $params
   *   The query parameters.
   *
   * @return \League\Uri\Contracts\UriInterface
   *   The API uri.
   */
  private function getApiUri(string $language, array $params) : UriInterface {
    return Uri::createFromString(
      sprintf('%s/jsonapi/node/news', $this->getBaseUri($language))
    )->withQuery(\GuzzleHttp\http_build_query($params));
  }

  /**
   * Gets the URI to given content.
   *
   * @param string $language
   *   The content uri.
   * @param string $path
   *   The path.
   *
   * @return \League\Uri\Contracts\UriInterface
   *   The URI to content.
   */
  private function getContentUri(string $language, string $path) : UriInterface {
    return Uri::createFromString(
      sprintf('%s/%s', $this->getBaseUri($language), ltrim($path, '/'))
    );
  }

  /**
   * Create entity for given API response item.
   *
   * @param object $item
   *   The API response item.
   *
   * @return \Drupal\helfi_news_feed\RestResponse\News
   *   The news DTO.
   */
  private function createEntity(\stdClass $item) : News {
    $entity = new News(
      $item->id,
      $item->attributes->title,
      $item->attributes->langcode,
      $item->attributes->created,
      $item->attributes->changed,
      $this->getContentUri($item->attributes->langcode, $item->attributes->path->alias)
    );

    return $entity;
  }

  /**
   * Gets a list of 'news_item' entities for given paragraph.
   *
   * @param \Drupal\helfi_news_feed\Entity\NewsFeedParagraph $paragraph
   *   The news feed paragraph.
   * @param string $language
   *   The language.
   *
   * @return \Drupal\helfi_news_feed\RestResponse\NewsResponse
   *   The response.
   *
   * @throws \Drupal\api_tools\Exception\ErrorResponseException
   */
  public function list(NewsFeedParagraph $paragraph, string $language) : NewsResponse {
    $params = [
      'page[limit]' => $paragraph->getLimit(),
      'filter[langcode]' => $language,
    ];
    if ($tags = $paragraph->getTags()) {
      foreach ($tags as $key => $tag) {
        // Only show entities that contain ALL defined tags, like
        // (WHERE tag = 'first' AND tag = 'second').
        $params += [
          sprintf('filter[tags-%s-and][group][conjunction]', $key) => 'AND',
          sprintf('filter[tag-%s][condition][path]', $key) => 'news_item_tags.name',
          sprintf('filter[tag-%s][condition][value]', $key) => $tag,
          sprintf('filter[tag-%s][condition][memberOf]', $key) => sprintf('tags-%s-and', $key),
        ];
      }
    }
    $request = new Request($this->getApiUri($language, $params));

    /** @var \Drupal\helfi_news_feed\RestResponse\NewsResponse $response */
    $response = $this->request($request, function (ResponseInterface $response) use ($language) {
      $json = \GuzzleHttp\json_decode($response->getBody()->getContents());

      $entities = array_map(function (\stdClass $item) : News {
        return $this->createEntity($item);
      }, $json->data);

      yield new NewsResponse($entities);
    });
    return $response;
  }

}
