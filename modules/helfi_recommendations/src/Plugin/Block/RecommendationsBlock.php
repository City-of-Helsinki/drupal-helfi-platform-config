<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_recommendations\RecommendationManagerInterface;

/**
 * Provides 'AI powered recommendations'.
 */
#[Block(
  id: "helfi_recommendations",
  admin_label: new TranslatableMarkup("AI powered recommendations"),
)]
final class RecommendationsBlock extends BlockBase implements ContainerFactoryPluginInterface, ContextAwarePluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly RecommendationManagerInterface $recommendationManager,
    private readonly EntityVersionMatcher $entityVersionMatcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
