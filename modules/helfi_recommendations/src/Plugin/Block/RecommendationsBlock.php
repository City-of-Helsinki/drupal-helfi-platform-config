<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_recommendations\RecommendationsLazyBuilder;
use Drupal\helfi_recommendations\RecommendationManagerInterface;
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
    $htmx = new Htmx();
    $htmx->get(new Url('helfi_recommendations.htmx', [
      'type' => $entity->getEntityTypeId(),
      'id' => $entity->id(),
    ]))
      ->trigger('load');

    $build = [];
    $build['recommendations'] = [
      '#theme' => 'helfi_htmx_preview',
      '#num_items' => 3,
      '#message' => new TranslatableMarkup('Loading recommendations', options: [
        'context' => 'Recommendations loading message',
      ]),
      '#wrapper' => 'ul',
      '#attributes' => ['class' => ['recommendations--list__recommendations']],
    ];
    $htmx->applyTo($build['recommendations']);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResult {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    ['entity' => $entity] = $this->entityVersionMatcher->getType();

    if (!$entity instanceof ContentEntityInterface) {
      return AccessResult::forbidden();
    }

    if ($this->recommendationManager->showRecommendations($entity)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
