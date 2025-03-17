<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\helfi_recommendations\RecommendationManager;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides 'AI powered recommendations'.
 */
#[Block(
  id: "helfi_recommendations",
  admin_label: new TranslatableMarkup("AI powered recommendations"),
)]
final class RecommendationsBlock extends BlockBase implements ContainerFactoryPluginInterface, ContextAwarePluginInterface {

  // Default cache max age is 1 hour.
  const CACHE_MAX_AGE = 3600;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RecommendationManager $recommendationManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly LoggerInterface $logger,
    private readonly EntityVersionMatcher $entityVersionMatcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    return new self($configuration, $plugin_id, $plugin_definition,
      $container->get(RecommendationManager::class),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('logger.channel.helfi_recommendations'),
      $container->get('helfi_platform_config.entity_version_matcher'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    ['entity' => $entity] = $this->entityVersionMatcher->getType();

    // @todo Implement theme layer.
    $response = [
      '#theme' => 'recommendations_block',
      '#title' => $this->t('You might be interested in', [], ['context' => 'Recommendations block title']),
    ];

    $recommendations = $this->getRecommendations($entity);
    if (!$recommendations) {
      if ($this->currentUser->isAnonymous()) {
        return [];
      }

      $response['#no_results_message'] = $this->t('No recommended content has been created for this page yet.', [], ['context' => 'Helfi AI recommendations']);
      return $response;
    }

    // @todo Preprocess recommendations prior to rendering.
    // We can't use the regular entity rendering process because
    // (all of) the recommendations are not nodes in this Drupal
    // instance. External entities would've been a viable solution
    // here, but there's already a huge refactoring need for current
    // usage to get the codebase D11 compatible.
    $response['#rows'] = $recommendations;

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(
      parent::getCacheContexts(),
      ['languages:language_content', 'user.roles:anonymous', 'url.path'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    $max_age = self::CACHE_MAX_AGE;

    // Applied contexts can affect the cache max age when this plugin is
    // involved in caching, collect and return them.
    foreach ($this->getContexts() as $context) {
      if ($context instanceof CacheableDependencyInterface) {
        $max_age = Cache::mergeMaxAges($max_age, $context->getCacheMaxAge());
      }
    }

    return $max_age;
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
    try {
      $recommendations = $this->recommendationManager
        ->getRecommendations($entity, 3, $entity->language()->getId());
      return $recommendations;
    }
    catch (\Exception $exception) {
      Error::logException($this->logger, $exception);
      return [];
    }
  }

}
