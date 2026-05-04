<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for Recommendations HTMX response.
 */
final readonly class HtmxController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    private RecommendationManagerInterface $recommendationManager,
    private AccountProxyInterface $currentUser,
    private LanguageManagerInterface $languageManager,
  ) {
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
    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    if (!$entity->hasTranslation($langcode)) {
      return [];
    }
    $entity = $entity->getTranslation($langcode);

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
   * Gets the entity from request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity.
   */
  private function getEntityFromRequest(Request $request): ContentEntityInterface {
    $entityTypeId = $request->attributes->get('entity_type_id');
    $entity = $request->attributes->get($entityTypeId);

    if ((!$entity instanceof ContentEntityInterface) || !$this->recommendationManager->showRecommendations($entity)) {
      throw new AccessDeniedHttpException();
    }

    return $entity;
  }

  /**
   * A HTMX callback for Recommendations list.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array<mixed>
   *   A render array of results.
   */
  public function content(Request $request): array {
    $entity = $this->getEntityFromRequest($request);
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

    foreach ($recommendations as $recommendation) {
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
      $build['#rows'][] = $recommendation;

    }
    return $build;
  }

}
