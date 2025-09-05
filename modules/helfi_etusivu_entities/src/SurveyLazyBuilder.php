<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Drupal\helfi_etusivu_entities\Plugin\Block\SurveyBlock;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lazy builder for survey.
 */
final class SurveyLazyBuilder extends LazyBuilderBase {

  /**
   * The constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\helfi_api_base\Language\DefaultLanguageResolver $defaultLanguageResolver
   *   Default language resolver.
   */
  public function __construct(
    #[Autowire(service: 'logger.channel.helfi_etusivu_entities')]
    protected LoggerInterface $logger,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected LanguageManagerInterface $languageManager,
    protected DefaultLanguageResolver $defaultLanguageResolver,
  ) {
    parent::__construct(
      $this->entityTypeManager,
      $this->routeMatch,
      $this->languageManager,
      $this->defaultLanguageResolver,
    );
  }

  /**
   * Lazy builder callback function.
   *
   * @param bool $useRemoteEntities
   *   Render entities from remote source.
   *
   * @return array
   *   The render array.
   */
  public function lazyBuild(bool $useRemoteEntities): array {
    try {
      $local = $this->getLocalEntities();
      $remote = $this->getExternalEntityStorage('helfi_surveys')
        ->loadMultiple();

      // Some non-core instances might want to show only local entities.
      // Block configuration allows disabling the remote entities.
      $remote = $useRemoteEntities && $remote ? $this->handleRemoteEntities($remote) : [];
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return [];
    }

    $sorted = $this->sortEntities($local, $remote);

    return $this
      ->entityTypeManager
      ->getViewBuilder('node')
      ->viewMultiple($sorted, 'default');
  }

  /**
   * Gets a list of local entities the block should render.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Items the block should render.
   *
   * @throws \Exception
   */
  protected function getLocalEntities() : array {
    $langcodes = $this->getContentLangcodes();

    $storage = $this->entityTypeManager->getStorage('node');

    // Get all published survey nodes.
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'survey')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $langcodes, 'IN')
      ->sort('published_at', 'DESC');

    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'survey');

    // Query only local nodes.
    if (isset($fields['field_publish_externally'])) {
      $query->condition('field_publish_externally', FALSE);
    }

    return array_values($storage->loadMultiple($query->execute()));
  }

  /**
   * Handle the external entities.
   *
   * @return array
   *   Remote entities.
   */
  protected function handleRemoteEntities(array $remoteEntities): array {
    $nodes = [];

    /** @var \Drupal\external_entities\Entity\ExternalEntityInterface $entity */
    foreach ($remoteEntities as $entity) {
      $linkUrl = NULL;
      $linkText = NULL;
      if ($entity->hasField('survey_link_text')) {
        $linkText = $entity->get('survey_link_text')->value;
        $linkUrl = $entity->get('survey_link_url')->value;
      }

      // Create nodes for the block based on external entity data.
      $nodes[] = Node::create([
        'uuid' => $entity->get('uuid')->value,
        'type' => 'survey',
        'field_survey_link' => ['uri' => $linkUrl, 'title' => $linkText],
        'langcode' => $entity->get('langcode')->value,
        'body' => Xss::filter($entity->get('body')->value),
        'title' => Xss::filter($entity->get('title')->value),
        'status' => $entity->get('status')->value,
      ]);
    }

    return $nodes;
  }

  /**
   * Sort the entities.
   *
   * @param array $local
   *   Local entities.
   * @param array $remote
   *   Remote entities.
   *
   * @return array
   *   An array of sorted entities.
   *
   * @codeCoverageIgnore
   */
  protected function sortEntities(array $local, array $remote) : array {
    $currentEntity = $this->getCurrentPageEntity(array_keys(SurveyBlock::ENTITY_TYPE_FIELDS));

    $surveys = array_merge($remote, $local);

    // Sort by publised_at time.
    usort($surveys, static function (NodeInterface $a, NodeInterface $b) {
      $weightA = $a->get('published_at')->value;
      $weightB = $b->get('published_at')->value;
      if ($weightA === $weightB) {
        return 0;
      }
      return $weightA < $weightB ? 1 : -1;
    });

    $referenceField = SurveyBlock::ENTITY_TYPE_FIELDS[$currentEntity?->getEntityTypeId()] ?? NULL;

    // Pick which survey to show.
    foreach ($surveys as $node) {
      // Check if the node should be shown at all pages.
      if ($node->get('field_survey_content_pages')->isEmpty()) {
        return [$node];
      }

      // Show survey if current page's entity is found
      // from the list of referenced entities.
      if (!empty($referenceField) && $this->hasReference($referenceField, $node, $currentEntity)) {
        return [$node];
      }
    }

    return [];
  }

}
