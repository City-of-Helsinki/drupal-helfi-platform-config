<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_recommendations\RecommendationsLazyBuilder;
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

    if (!$entity instanceof ContentEntityInterface) {
      return [];
    }

    $build = [];

    $build['recommendations'] = [
      '#cache' => [
        'contexts' => $this->getCacheContexts(),
        'tags' => $this->getCacheTags(),
      ],
      '#lazy_builder' => [
        RecommendationsLazyBuilder::class . ':build',
        [
          'isAnonymous' => $this->currentUser->isAnonymous(),
          'entityType' => $entity->getEntityTypeId(),
          'entityId' => $entity->id(),
          'langcode' => $entity->language()->getId(),
        ],
      ],
      '#create_placeholder' => TRUE,
      '#lazy_builder_preview' => ['#markup' => ''],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // @todo "user.roles" context is needed while cross-instance
    // recommendations are in review mode as part of the content is
    // displayed to selected roles only. We can replace it with
    // "user.roles:anonymous" once we have validated the cross-instance
    // recommendations work as intended.
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

    if (!$entity instanceof ContentEntityInterface) {
      return parent::getCacheTags();
    }

    return Cache::mergeTags(parent::getCacheTags(), $entity->getCacheTags());
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

}
