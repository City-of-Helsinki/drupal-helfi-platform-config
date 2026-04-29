<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_recommendations\RecommendationManagerInterface;

/**
 * Controller for Recommendations HTMX response.
 */
final readonly class HtmxController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private RecommendationManagerInterface $recommendationManager,
    private EntityTypeManagerInterface $entityTypeManager,
    private AccountProxyInterface $currentUser,
    private LanguageManagerInterface $languageManager,
  ) {
  }

  private function getEntity(string $entityType, string $entityId): ?ContentEntityInterface {
    try {
      $langcode = $this->languageManager
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
        ->getId();

      $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);

      if (!$entity instanceof ContentEntityInterface) {
        return NULL;
      }

      if ($entity->language()->getId() === $langcode) {
        return $entity;
      }
      return $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException) {
    }
    return NULL;
  }

  /**
   * Get the recommendations for current content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity to find recommendations for.
   *
   * @return array<mixed>
   *   Array of recommendations
   */
  private function getRecommendations(ContentEntityInterface $entity): array {
    $recommendations = [];

    try {
      $recommendations = $this->recommendationManager
        ->getRecommendations(
          $entity,
          3,
          $entity->language()->getId(),
        );
    }
    catch (\Exception) {
    }
    return $recommendations;
  }

  /**
   * A HTMX callback for Recommendations list.
   *
   * @param string $type
   *   The entity type.
   * @param string $id
   *   The entity id.
   *
   * @return array<mixed>
   *   A render array of results.
   */
  public function content(string $type, string $id): array {
    if (!$entity = $this->getEntity($type, $id)) {
      return [];
    }
    $canSeeScore = $this->currentUser->hasPermission('view recommendation score');

    $build = [
      '#theme' => 'recommendations_block',
      '#cache' => [
        'contexts' => [
          'languages:language_content',
          'user.roles',
          'url.path',
        ],
        'tags' => Cache::mergeTags($entity->getCacheTags(), [$this->recommendationManager->getCacheTagForAll()]),
      ],
      '#entity_type' => $entity->bundle(),
    ];

    $recommendations = $this->getRecommendations($entity);

    if (!$recommendations) {
      if ($canSeeScore) {
        $build['#no_results_message'] = new TranslatableMarkup('No recommended content has been created for this page yet.', options: [
          'context' => 'helfi_recommendations',
        ]);
      }

      return $build;
    }

    // @todo Preprocess recommendations prior to rendering.
    // We can't use the regular entity rendering process because
    // (all of) the recommendations are not nodes in this Drupal
    // instance. External entities would've been a viable solution
    // here, but there's already a huge refactoring need for current
    // usage to get the codebase D11 compatible. Let's revisit this
    // once we've updated to D11.
    $build['#rows'] = $recommendations;
    foreach ($build['#rows'] as &$recommendation) {
      // Show recommendation score to users who have the permission.
      if ($canSeeScore && !empty($recommendation['score'])) {
        $recommendation['helptext'] = new TranslatableMarkup('Search result score: @score', [
          '@score' => $recommendation['score'],
        ]);
      }

      if (!empty($recommendation['uuid'])) {
        $build['#cache']['tags'][] = $this->recommendationManager
          ->getCacheTagForUuid($recommendation['uuid']);
      }
    }

    return $build;
  }

  /**
   * Checks if user has access to view the given entity.
   *
   * @param string $type
   *   The entity type.
   * @param string $id
   *   The entity id.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(string $type, string $id): AccessResultInterface {
    if (!$entity = $this->getEntity($type, $id)) {
      return AccessResult::forbidden();
    }

    return $entity->access('view', return_as_object: TRUE);
  }

}
