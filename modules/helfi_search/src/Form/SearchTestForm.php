<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\helfi_search\EmbeddingsModelInterface;
use Drupal\helfi_search\TokenUsageTracker;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Search embeddings test form.
 *
 * This form is meant as a pathfinder for evaluating the search
 * embeddings for Helfi. This should be considered as throwaway code.
 *
 * Some ideas for future improvements:
 *  - Flood protection for search.
 */
class SearchTestForm extends FormBase {

  /**
   * Map of pricing per million tokens.
   *
   * @link https://platform.openai.com/docs/pricing
   */
  private const array PRICING_PER_M = [
    'text-embedding-3-small' => 0.02,
  ];

  use AutowireTrait;

  /**
   * Elasticsearch index name.
   */
  const string INDEX_NAME = 'embeddings';

  public function __construct(
    protected EmbeddingsModelInterface $embeddingsModel,
    protected TokenUsageTracker $tokenUsageTracker,
    protected LanguageManagerInterface $languageManager,
    #[Autowire(service: 'helfi_platform_config.etusivu_elastic_client')]
    protected Client $elasticClient,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'helfi_search_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['search_query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Query'),
      '#description' => $this->t('Enter a search query.'),
      '#required' => TRUE,
      '#maxlength' => 500,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];

    // Display results if available.
    if ($form_state->has('embeddings_result')) {
      $form['results'] = [
        '#type' => 'details',
        '#title' => $this->t('Results'),
        '#open' => TRUE,
        '#weight' => 10,
      ];

      $result = $form_state->get('embeddings_result');

      // Display embeddings info if embeddings were generated successfully.
      if (isset($result['embeddings'])) {
        $form['results']['query'] = [
          '#type' => 'item',
          '#title' => $this->t('Query'),
          '#markup' => '<strong>' . htmlspecialchars($result['query']) . '</strong>',
        ];

        $form['results']['vector'] = [
          '#type' => 'details',
          '#title' => $this->t('Embedding Vector'),
          '#open' => FALSE,
          '#weight' => 20,
        ];
        $form['results']['vector']['vector_preview'] = [
          '#type' => 'item',
          '#title' => $this->t('Generated @dimensions-dimensional vector', ['@dimensions' => count($result['embeddings'])]),
          '#markup' => '<code>[' . implode(', ', array_map(fn($v) => number_format($v, 6), $result['embeddings'])) . ']</code>',
        ];

        // Display search results if available.
        if (!empty($result['search_results'])) {
          $rows = [];
          foreach ($result['search_results'] as $hit) {
            $excerpt = mb_strimwidth($hit['content'], 0, 200, '...');

            $rows[] = [
              'score' => number_format($hit['score'], 4),
              'entity_type' => htmlspecialchars($hit['entity_type']),
              'url' => $hit['url'] ? Link::fromTextAndUrl(htmlspecialchars($hit['title']), Url::fromUserInput($hit['url'])) : '-',
              'language' => htmlspecialchars($hit['language']),
              'content' => htmlspecialchars($excerpt),
            ];
          }

          $form['results']['search_results'] = [
            '#type' => 'table',
            '#header' => [
              $this->t('Score'),
              $this->t('Entity Type'),
              $this->t('URL'),
              $this->t('Language'),
              $this->t('Content'),
            ],
            '#rows' => $rows,
            '#empty' => $this->t('No similar documents found.'),
          ];
        }
      }
    }

    // Display token usage statistics.
    $form['token_stats'] = [
      '#type' => 'details',
      '#title' => $this->t('Token Usage Statistics'),
      '#open' => FALSE,
      '#weight' => 20,
    ];

    $usage_by_model = $this->tokenUsageTracker->getTokenUsage();
    if ($usage_by_model) {
      $items = [];
      foreach ($usage_by_model as $model => $tokens) {
        $price = self::PRICING_PER_M[$model] ?? 0;
        $items[] = $this->t('@model: @tokens tokens (approximate cost: @price @unit)', [
          '@model' => $model,
          '@tokens' => number_format($tokens),
          '@price' => $price ? ($tokens / 1000000 * $price) : $this->t('N/A'),
          '@unit' => '$',
        ]);
      }
      $form['token_stats']['by_model'] = [
        '#type' => 'item',
        '#title' => $this->t('Usage by Model'),
        '#markup' => '<ul><li>' . implode('</li><li>', $items) . "" . '</li></ul>',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $query = $form_state->getValue('search_query');

    try {
      // Generate embeddings for the query.
      $embeddings = $this->embeddingsModel->getEmbedding($query);

      // Get current language.
      $currentLanguage = $this->languageManager->getCurrentLanguage()->getId();

      // Perform vector similarity search.
      $results = $this->elasticClient->search([
        'index' => self::INDEX_NAME,
        'body' => [
          'knn' => [
            'field' => 'embeddings.vector',
            'query_vector' => $embeddings,
            'k' => 10,
            'num_candidates' => 100,
            "inner_hits" => [
              "_source" => FALSE,
              "fields" => [
                "embeddings.content",
              ],
              "size" => 1,
            ],
            'filter' => [
              'term' => [
                'search_api_language' => $currentLanguage,
              ],
            ],
          ],
          "size" => 10,
          '_source' => [
            'id',
            'entity_type',
            'url',
            'label',
            'search_api_language',
            'search_api_datasource',
          ],
        ],
      ])?->asArray() ?? [];

      // Process search results.
      $search_results = [];
      if (isset($results['hits']['hits'])) {
        foreach ($results['hits']['hits'] as $hit) {
          $content = $hit['inner_hits']['embeddings']['hits']['hits'][0]['fields']['embeddings'][0]['content'][0] ?? '';

          $search_results[] = [
            'id' => $hit['_id'],
            'score' => $hit['_score'] ?? 0,
            'entity_type' => array_first($hit['_source']['entity_type'] ?? []),
            'url' => array_first($hit['_source']['url'] ?? []),
            'title' => array_first($hit['_source']['label'] ?? []),
            'language' => array_first($hit['_source']['search_api_language'] ?? []),
            'datasource' => array_first($hit['_source']['search_api_datasource'] ?? []),
            'content' => $content,
          ];
        }
      }

      $form_state->set('embeddings_result', [
        'query' => $query,
        'embeddings' => $embeddings,
        'search_results' => $search_results,
        'total_hits' => $results['hits']['total']['value'] ?? 0,
      ]);

      $this->messenger()->addStatus($this->t('Successfully generated embeddings and found @count similar documents for "@query"', [
        '@query' => $query,
        '@count' => count($search_results),
      ]));
    }
    catch (ElasticsearchException | TransportException $e) {
      // Elasticsearch-specific errors.
      $this->messenger()->addError($this->t('Failed to query Elasticsearch: @error', ['@error' => $e->getMessage()]));
    }
    catch (\Exception $e) {
      // General errors (including embedding generation failures).
      $this->messenger()->addError($this->t('Failed to generate embeddings: @error', ['@error' => $e->getMessage()]));
    }

    $form_state->setRebuild();
  }

}
