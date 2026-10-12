<?php

declare(strict_types=1);

namespace Drupal\helfi_search\SimilarContent;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\helfi_search\DocumentKeyTrait;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shows the markdown of an entity next to the markdown of similar content.
 */
final class SimilarContentController extends ControllerBase {

  use DocumentKeyTrait;

  /**
   * Static cache so title() and view() share one query.
   *
   * @phpstan-var array<string, \Drupal\helfi_search\SimilarContent\SimilarDocument[]>
   */
  private array $documents = [];

  public function __construct(
    private readonly Connection $database,
    private readonly SimilarContentFinder $finder,
    #[Autowire(service: 'logger.channel.helfi_search')]
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Builds the side-by-side view.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The translation to show.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public function view(EntityInterface $entity, string $langcode): array {
    if (!$entity instanceof ContentEntityInterface || !$entity->hasTranslation($langcode)) {
      throw new NotFoundHttpException();
    }

    $translation = $entity->getTranslation($langcode);

    return [
      '#theme' => 'helfi_search_similar_content',
      '#markdown' => $this->getMarkdown($translation) ?? '',
      '#documents' => $this->documents($translation),
      '#edit_url' => $translation->hasLinkTemplate('edit-form') ? $translation->toUrl('edit-form') : NULL,
      '#attached' => ['library' => ['helfi_search/similar_content']],
      '#cache' => [
        'max-age' => 0,
        'tags' => $translation->getCacheTags(),
        'contexts' => ['url', 'user.permissions'],
      ],
    ];
  }

  /**
   * Builds the page title.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The translation to show.
   */
  public function title(EntityInterface $entity, string $langcode): TranslatableMarkup {
    if (!$entity instanceof ContentEntityInterface || !$entity->hasTranslation($langcode)) {
      throw new NotFoundHttpException();
    }

    $translation = $entity->getTranslation($langcode);

    return $this->formatPlural(
      count($this->documents($translation)),
      'Similar content (1 result)',
      'Similar content (@count results)',
      options: ['context' => 'Helfi search'],
    );
  }

  /**
   * Reads the entity's stored markdown.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to read.
   *
   * @return string|null
   *   The markdown of the whole document, or NULL if it has not been
   *   processed yet.
   */
  private function getMarkdown(ContentEntityInterface $entity): ?string {
    $query = $this->database->select(self::DOCUMENT_TABLE, 'd')
      ->fields('d', ['markdown']);

    $markdown = self::keyCondition($query, $entity)
      ->execute()
      ->fetchField();

    return is_string($markdown) ? $markdown : NULL;
  }

  /**
   * Finds the similar documents once per translation.
   *
   * @return \Drupal\helfi_search\SimilarContent\SimilarDocument[]
   *   The similar documents.
   */
  private function documents(ContentEntityInterface $translation): array {
    $key = $translation->getEntityTypeId() . ':' . $translation->id() . ':' . $translation->language()->getId();

    if (!isset($this->documents[$key])) {
      try {
        $this->documents[$key] = $this->finder->findSimilar($translation);
      }
      catch (ElasticsearchException | TransportException $e) {
        Error::logException($this->logger, $e);
        $this->documents[$key] = [];
      }
    }

    return $this->documents[$key];
  }

}
