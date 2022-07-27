<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service class for global navigation related functions.
 */
class GlobalNavigationService implements ContainerInjectionInterface {

  /**
   * Current environment.
   *
   * @var \Drupal\helfi_api_base\Environment\Environment
   */
  protected Environment $currentProject;

  /**
   * Current environment (local/test/prod).
   *
   * @var string
   */
  protected string $env;

  /**
   * Construct an instance.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $dataCache
   *   The data cache.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver
   *   EnvironmentResolver helper class.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(
    protected CacheBackendInterface $dataCache,
    protected ClientInterface $httpClient,
    protected EnvironmentResolver $environmentResolver,
    protected LanguageManagerInterface $languageManager,
    protected LoggerInterface $logger,
    protected RequestStack $requestStack
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('cache.default'),
      $container->get('http_client'),
      $container->get('helfi_api_base.environment_resolver'),
      $container->get('language_manager'),
      $container->get('logger.channel.helfi_global_navigation'),
      $container->get('request_stack')
    );
  }

  /**
   * Return current env.
   *
   * @return string
   *   The env.
   */
  public function getEnv(): string {
    if (!isset($this->env)) {
      if ($env = getenv('APP_ENV')) {
        $this->env = $this->environmentResolver::getCurrentEnvironmentName($env);
        return $this->env;
      }
      throw new \InvalidArgumentException(sprintf('No environment found for %s', $env));
    }
    return $this->env;
  }

  /**
   * Return the current project's environment.
   *
   * @return \Drupal\helfi_api_base\Environment\Environment
   *   Current project's environment.
   */
  public function getCurrentProject(): Environment {
    if (!isset($this->currentProject)) {
      $current_host = $this->requestStack->getCurrentRequest()->getHost();
      if ($environment = $this->environmentResolver->getEnvironmentByUrl($current_host)) {
        $this->currentProject = $environment;
        return $environment;
      }
      throw new \InvalidArgumentException(sprintf('No environment found for host %s', $current_host));
    }

    return $this->currentProject;
  }

  /**
   * Check if current instance is frontpage.
   */
  public function inFrontPage(): bool {
    return $this->getCurrentProject()->getId() === Project::ETUSIVU;
  }

  /**
   * Makes a request based on parameters and returns the response.
   *
   * @param string $project
   *   The project or instance to which the request is made.
   * @param string $method
   *   Request method.
   * @param string $endpoint
   *   The endpoint in the instance.
   * @param array $options
   *   Body for requests.
   *
   * @return string
   *   The response body.
   */
  public function makeRequest(string $project, string $method, string $endpoint, array $options = []): string {
    $url = $this->getProjectUrl($project) . $endpoint;

    // Disable SSL verification in local environment.
    if ($this->getEnv() === 'local') {
      $options['verify'] = FALSE;
      $url = str_replace('https://', '', $url);
      $url = str_replace('.so/', '.so:8080/', $url);
    }

    if ($method === 'GET') {
      return $this->getContent($url, $options);
    }

    try {
      $response = $this->httpClient->request($method, $url, $options);
      return $response->getBody()->getContents();
    }
    catch (\throwable $e) {
      $this->logger->error('Request failed with error: ' . $e->getMessage());
      return '';
    }
  }

  /**
   * Make a get request and cache results.
   *
   * @param string $url
   *   The url for the request.
   * @param array $options
   *   Possible options for the request.
   *
   * @return string
   *   The response body.
   */
  public function getContent(string $url, array $options = []): string {
    if ($data = $this->getFromCache($url)) {
      return $data;
    }

    try {
      $response = $this->httpClient->request('GET', $url, $options);
      $content = $response->getBody()->getContents();
      $this->setCache($url, $content);

      return $content;
    }
    catch (\throwable $e) {
      $this->logger->error('Request failed with error: ' . $e->getMessage());

      // Check invalidated cache if no valid response received.
      if ($data = $this->getFromCache($url, TRUE)) {
        return $data;
      }

      return '';
    }
  }

  /**
   * Gets the cache key for given id.
   *
   * @param string $id
   *   The id.
   *
   * @return string
   *   The cache key.
   */
  protected function getCacheKey(string $id): string {
    $id = preg_replace('/[^a-z0-9_]+/s', '_', $id);

    return sprintf('global-navigation-%s', $id);
  }

  /**
   * Gets cached data for given id.
   *
   * @param string $id
   *   The id.
   * @param bool $allow_invalid
   *   Return invalidated cache items also.
   *
   * @return string|null
   *   The cached data or null.
   */
  protected function getFromCache(string $id, $allow_invalid = FALSE):? string {
    $key = $this->getCacheKey($id);

    if (isset($this->data[$key])) {
      return $this->data[$key];
    }

    if ($data = $this->dataCache->get($key, $allow_invalid)) {
      return $data->data;
    }
    return NULL;
  }

  /**
   * Sets the cache.
   *
   * @param string $id
   *   The id.
   * @param mixed $data
   *   The data.
   */
  protected function setCache(string $id, $data): void {
    $key = $this->getCacheKey($id);
    $this->dataCache->set($key, $data, $this->getCacheMaxAge(), []);
  }

  /**
   * Return cache max age.
   *
   * @return int
   *   The cache max age.
   */
  public function getCacheMaxAge() : int {
    return time() + 60 * 60;
  }

  /**
   * Return frontpage project.
   *
   * @return \Drupal\helfi_api_base\Environment\Environment
   *   The frontpage project.
   */
  protected function getFrontPage(): Environment {
    return $this->environmentResolver->getEnvironment(Project::ETUSIVU, $this->getEnv());
  }

  /**
   * Return project's environment-specific URL with correct language parameter.
   *
   * @param string $project
   *   Project id.
   * @param string|null $lang_code
   *   Language code.
   *
   * @return string
   *   The URL.
   */
  public function getProjectUrl(string $project, string $lang_code = NULL): string {
    if (!$lang_code) {
      $lang_code = $this->languageManager->getCurrentLanguage()->getId();
    }
    try {
      return $this->environmentResolver->getEnvironment($project, $this->getEnv())->getUrl($lang_code);
    }
    catch (\Exception $e) {
      $this->logger->warning('Cannot retrieve project URL with provided language. ' . $e->getMessage());
      return '';
    }
  }

}
