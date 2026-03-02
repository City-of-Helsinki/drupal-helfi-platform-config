<?php

declare(strict_types=1);

namespace Drupal\helfi_search\OpenAI;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_search\EmbeddingsModelException;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\MissingConfigurationException;
use Drupal\helfi_search\OpenAI\DTO\Response;
use Drupal\helfi_search\TokenUsageTracker;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;

/**
 * OpenAI Embeddings API.
 */
class EmbeddingsApi implements EmbeddingsModelInterface {

  /**
   * API version.
   */
  const string API_VERSION = '2024-10-21';

  /**
   * Max input length.
   */
  const int MAX_INPUT_LENGTH = 8000;

  public function __construct(
    private readonly ClientInterface $client,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TokenUsageTracker $tokenUsageTracker,
  ) {
  }

  /**
   * Make request to OpenAI API.
   *
   * @throws \Drupal\helfi_search\EmbeddingsModelException
   */
  private function makeRequest(string|array $input): Response {
    $config = $this->configFactory->get('helfi_search.settings');
    $apiKey = $config->get('openai_api_key');
    $baseUrl = $config->get('openai_base_url');
    $model = $config->get('openai_model');

    if (empty($apiKey) || empty($baseUrl) || empty($model)) {
      throw new MissingConfigurationException('OpenAI API key not configured');
    }

    if (!is_array($input)) {
      $input = [$input];
    }

    // Truncate long strings.
    $input = array_map(static fn ($item) => Unicode::truncate($item, self::MAX_INPUT_LENGTH, TRUE), $input);

    try {
      $response = $this->client->request('POST', $baseUrl . '/embeddings', [
        'query' => [
          'api-version' => self::API_VERSION,
        ],
        'headers' => [
          'Authorization' => "Bearer $apiKey",
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'model' => $model,
          'input' => $input,
        ],
      ]);

      $body = Utils::jsonDecode($response->getBody()->getContents());

      if (!isset($body->data) || !is_array($body->data)) {
        throw new EmbeddingsModelException('Invalid response format from OpenAI API');
      }

      $response = new Response(
        $body->model ?? '',
        array_column($body->data, 'embedding'),
        $body->usage->total_tokens ?? 0
      );

      // Track token usage.
      if ($response->total_tokens > 0) {
        $this->tokenUsageTracker->updateTokenUsage($response->model, $response->total_tokens);
      }

      return $response;
    }
    catch (GuzzleException $e) {
      throw new EmbeddingsModelException($e->getMessage(), previous: $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEmbedding(string $text): array {
    return array_first($this->makeRequest($text)->embedding) ?? throw new EmbeddingsModelException('No embedding found');
  }

  /**
   * {@inheritdoc}
   */
  public function batchGetEmbedding(array $batch): array {
    if (empty($batch)) {
      return [];
    }

    // @fixme This is not the batch API which makes cheaper requests.
    // The cheaper requests are run asynchronously. Getting the batch
    // API to work with search_api does not seem trivial.
    // See https://platform.openai.com/docs/guides/batch.
    $embeddings = $this->makeRequest(array_values($batch))->embedding;

    return array_combine(array_keys($batch), $embeddings);
  }

}
