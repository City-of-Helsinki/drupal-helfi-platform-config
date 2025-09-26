<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Client;

use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_api_base\TextConverter\TextConverterManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;

/**
 * The keyword generator.
 */
final class ApiClient {

  /**
   * Maximum batch size.
   *
   * @link https://ai.finto.fi/v1/ui/#/Automatic%20subject%20indexing/annif.rest.suggest_batch
   *
   * @var int
   */
  public const MAX_BATCH_SIZE = 32;

  /**
   * Constructs a new instance.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Drupal\helfi_api_base\TextConverter\TextConverterManager $textConverter
   *   The text converter.
   */
  public function __construct(
    private readonly ClientInterface $client,
    private readonly TextConverterManager $textConverter,
  ) {
  }

  /**
   * Get default options for the API.
   *
   * @return array
   *   Finto API configuration.
   */
  private function getDefaultOptions() : array {
    return [
      // Maximum number of results to return.
      'limit' => 20,
      // Minimum score threshold, below which results will not be returned.
      'threshold' => 0,
    ];
  }

  /**
   * Generate keywords for given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\helfi_recommendations\Client\Keyword[]|null
   *   Keywords or NULL if unsupported entity.
   *
   * @throws \Drupal\helfi_recommendations\Client\ApiClientException
   *   If keyword generator returns an error.
   */
  public function suggest(EntityInterface $entity) : ?array {
    $language = $entity->language()->getId();
    $project = $this->getProject($language);

    // Exit early if suggestions for this language are not supported.
    if (!$project) {
      return NULL;
    }

    $text = $this->textConverter->convert($entity);
    if (!$text) {
      return NULL;
    }

    try {
      $response = $this->client->request('POST', "https://ai.finto.fi/v1/projects/$project/suggest", [
        'headers' => [
          'Accept' => 'application/json',
        ],
        'form_params' => $this->getDefaultOptions() + [
          'language' => $language,
          'text' => $text,
        ],
      ]);

      return $this->mapResults(Utils::jsonDecode($response->getBody()->getContents()));
    }
    catch (GuzzleException $e) {
      throw new ApiClientException($e->getMessage(), previous: $e);
    }
  }

  /**
   * Generate keywords for batch of entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   Batch of entities. Batch entities cannot mix languages.
   *
   * @return array<\Drupal\helfi_recommendations\Client\Keyword[]>
   *   Batch suggestion results keyed by input array keys. The
   *   results array might not contain all input entities.
   *
   * @throws \Drupal\helfi_recommendations\Client\ApiClientException
   *   If keyword generator returns an error.
   */
  public function suggestBatch(array $entities) : array {
    if (count($entities) > self::MAX_BATCH_SIZE) {
      throw new \InvalidArgumentException("Batch size must be <= " . self::MAX_BATCH_SIZE);
    }

    $language = NULL;
    $documents = [];

    foreach ($entities as $key => $entity) {
      if (!$language) {
        $language = $entity->language()->getId();
      }

      if ($language !== $entity->language()->getId()) {
        throw new \InvalidArgumentException("Batch requests cannot mix languages");
      }

      // It is important to skip empty content because the AI happily
      // generates nonsensical keywords for empty strings.
      $text = $this->textConverter->convert($entity);
      if (!$text) {
        continue;
      }

      $documents[] = [
        'document_id' => (string) $key,
        'text' => $text,
      ];
    }

    $project = $this->getProject($language);

    if (!$documents || !$project || !$language) {
      return [];
    }

    try {
      $query = $this->getDefaultOptions() + [
        'language' => $language,
      ];

      $response = $this->client->request('POST', "https://ai.finto.fi/v1/projects/$project/suggest-batch", [
        'query' => $query ,
        'headers' => [
          'Accept' => 'application/json',
        ],
        'json' => [
          'documents' => $documents,
        ],
      ]);

      return array_reduce(
        Utils::jsonDecode($response->getBody()->getContents()),
        function ($carry, $item) {
          $carry[$item->document_id] = $this->mapResults($item);
          return $carry;
        },
        []
      );
    }
    catch (GuzzleException $e) {
      throw new ApiClientException($e->getMessage(), previous: $e);
    }
  }

  /**
   * Get Annif project.
   *
   * For list of available projects, see https://ai.finto.fi/v1/projects.
   *
   * @return string|null
   *   Annif project id or NULL.
   */
  private function getProject(string $language) : ?string {
    if (!in_array($language, ['fi', 'sv', 'en'])) {
      return NULL;
    }

    return "yso-$language";
  }

  /**
   * Map API response to DTO.
   *
   * @return Keyword[]
   *   Array of keywords.
   */
  private function mapResults($results) : array {
    return array_map(
      static fn ($result) => new Keyword(
        $result->label,
        $result->score,
        $result->uri
      ),
      $results->results ?? []
    );
  }

}
