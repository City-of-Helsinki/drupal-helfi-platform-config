<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the recommendations block.
 */
final class RecommendationsLazyBuilder implements RecommendationsLazyBuilderInterface, TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() : array {
    return ['build'];
  }

  public function __construct(
    private readonly RecommendationManagerInterface $recommendationManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.helfi_recommendations')] private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function build(int $userId, string $entityType, string $entityId, string $langcode): array {
    $user = $this->entityTypeManager->getStorage('user')->load($userId);
    if (!$user instanceof UserInterface) {
      return [];
    }
    $isAnonymous = $user->isAnonymous();
    $canSeeScore = $user->hasPermission('view recommendation score');

    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    if (!$entity instanceof ContentEntityInterface) {
      return [];
    }

    if ($entity->language()->getId() !== $langcode) {
      if (!$entity->hasTranslation($langcode)) {
        return [];
      }

      $entity = $entity->getTranslation($langcode);
    }

    $response = [
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

    $recommendations = $entity instanceof ContentEntityInterface ? $this->getRecommendations($entity) : [];
    if (!$recommendations) {
      if ($isAnonymous) {
        return $response;
      }

      $response['#no_results_message'] = $this->t('No recommended content has been created for this page yet.', options: ['context' => 'helfi_recommendations']);
      return $response;
    }

    // @todo Preprocess recommendations prior to rendering.
    // We can't use the regular entity rendering process because
    // (all of) the recommendations are not nodes in this Drupal
    // instance. External entities would've been a viable solution
    // here, but there's already a huge refactoring need for current
    // usage to get the codebase D11 compatible. Let's revisit this
    // once we've updated to D11.
    $response['#rows'] = $recommendations;
    foreach ($response['#rows'] as &$recommendation) {
      // Show recommendation score to users who have the permission.
      if ($canSeeScore && !empty($recommendation['score'])) {
        $recommendation['helptext'] = $this->t('Search result score: @score', ['@score' => $recommendation['score']]);
      }

      if (!empty($recommendation['uuid'])) {
        $response['#cache']['tags'][] = $this->recommendationManager->getCacheTagForUuid($recommendation['uuid']);
      }
    }

    return $response;
  }

  /**
   * Get the recommendations for current content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity to find recommendations for.
   *
   * @return array
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
    catch (\Exception $exception) {
      Error::logException($this->logger, $exception);
      return [];
    }

    return $recommendations;
  }

}
