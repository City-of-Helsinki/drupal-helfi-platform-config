<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\OpenAI;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\helfi_search\OpenAI\EmbeddingsApi;
use Drupal\helfi_search\TokenUsageTracker;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EmbeddingsApi URL resolution.
 */
#[Group('helfi_search')]
class EmbeddingsApiTest extends TestCase {

  /**
   * Creates an EmbeddingsApi instance with the given base URL.
   */
  private function createApi(string $baseUrl, ClientInterface $client): EmbeddingsApi {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['openai_api_key', 'test-key'],
      ['openai_base_url', $baseUrl],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('helfi_search.settings')
      ->willReturn($config);

    $tokenUsageTracker = $this->createMock(TokenUsageTracker::class);

    return new EmbeddingsApi($client, $configFactory, $tokenUsageTracker);
  }

  /**
   * Creates a mock HTTP response with embedding data.
   */
  private function createEmbeddingResponse(): Response {
    return new Response(200, [], json_encode([
      'model' => 'text-embedding-3-small',
      'data' => [
        ['embedding' => [0.1, 0.2, 0.3]],
      ],
      'usage' => ['total_tokens' => 10],
    ]));
  }

  /**
   * Tests that standard OpenAI URL appends /embeddings.
   */
  public function testStandardOpenAiUrl(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        'https://api.openai.com/v1/embeddings',
        $this->anything(),
      )
      ->willReturn($this->createEmbeddingResponse());

    $api = $this->createApi('https://api.openai.com/v1', $client);
    $api->getEmbedding('test', 'text-embedding-3-small');
  }

  /**
   * Tests that Azure URL with {model} placeholder gets substituted.
   */
  public function testAzureUrlWithModelPlaceholder(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        'https://test.openai.azure.com/openai/deployments/text-embedding-3-small/embeddings',
        $this->anything(),
      )
      ->willReturn($this->createEmbeddingResponse());

    $api = $this->createApi(
      'https://test.openai.azure.com/openai/deployments/{model}',
      $client,
    );
    $api->getEmbedding('test', 'text-embedding-3-small');
  }

}
