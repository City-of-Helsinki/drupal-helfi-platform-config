<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Block;

use Drupal\Core\Access\AccessResult;
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
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
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
  // @todo Is this a good default? This max age will be set on
  // almost all pages when the block is rolled out to all instances
  // and most content types. We should consider implementing a
  // pubsub based cache tag purging mechanism instead, and have a
  // much higher value here (still need to update all of these in
  // regular basis to keep the recommendations relevant and up to
  // date).
  const CACHE_MAX_AGE = 3600;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RecommendationManagerInterface $recommendationManager,
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
      $container->get(RecommendationManagerInterface::class),
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

    $response = [
      '#theme' => 'recommendations_block',
      '#title' => $this->t('You might be interested in', [], ['context' => 'Recommendations block title']),
    ];

    $recommendations = $entity instanceof ContentEntityInterface ? $this->getRecommendations($entity) : [];
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
    // usage to get the codebase D11 compatible. Let's revisit this
    // once we've updated to D11.
    $response['#rows'] = $recommendations;

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // @todo "user.roles" context is needed while cross-instance
    // recommendations are in review mode as part of the content is
    // displayed to selected roles only. We can remove it once we
    // have validated the cross-instance recommendations work as
    // intended.
    return Cache::mergeContexts(
      parent::getCacheContexts(),
      ['languages:language_content', 'user.roles', 'url.path'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    ['entity' => $entity] = $this->entityVersionMatcher->getType();

    $recommendations = $entity instanceof ContentEntityInterface ? $this->getRecommendations($entity) : [];

    $tags = [];
    foreach ($recommendations as $recommendation) {
      if (!empty($recommendation['uuid'])) {
        $tags[] = "suggested_topics_uuid:{$recommendation['uuid']}";
      }
    }

    return Cache::mergeTags(parent::getCacheTags(), $tags);
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
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    ['entity' => $entity] = $this->entityVersionMatcher->getType();

    if (!$entity instanceof ContentEntityInterface) {
      return AccessResult::forbidden();
    }

    // @todo This is a temporary restriction to allow validating the
    // cross-instance recommendations in production before allowing the
    // use for all editors. Remove these once we have validated the
    // cross-instance recommendations work as intended.
    if ($entity->bundle() !== 'news_item' && $entity->bundle() !== 'news_article') {
      if (_helfi_recommendations_can_see_review_mode()) {
        return AccessResult::allowed();
      }

      return AccessResult::forbidden();
    }

    if ($entity instanceof ContentEntityInterface && $this->recommendationManager->showRecommendations($entity)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
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

    $options = [];
    if (in_array($entity->bundle(), ['news_item', 'news_article'])) {
      // @todo This is to preserve the functionality from the previous
      // implementation. Remove these once we have validated the
      // cross-instance recommendations work as intended.
      $options = [
        'instances' => ['etusivu'],
        'content_types' => ['node' => ['news_item', 'news_article']],
      ];
    }

    try {
      $recommendations = $this->recommendationManager
        ->getRecommendations(
          $entity,
          3,
          $entity->language()->getId(),
          $options,
        );
    }
    catch (\Exception $exception) {
      Error::logException($this->logger, $exception);
      return [];
    }

    return $recommendations;
  }

}
