<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_users\FavoriteStorage;
use Drupal\helfi_users\FavoriteToggleBuilder;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for favorite bulk actions.
 */
abstract class FavoriteActionBase extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  use DependencySerializationTrait;

  /**
   * Constructs a favorite action.
   *
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\helfi_users\FavoriteStorage $favoriteStorage
   *   The favorite storage.
   * @param \Drupal\helfi_users\FavoriteToggleBuilder $favoriteToggleBuilder
   *   The favorite toggle builder.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly FavoriteStorage $favoriteStorage,
    protected readonly FavoriteToggleBuilder $favoriteToggleBuilder,
    protected readonly AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(FavoriteStorage::class),
      $container->get(FavoriteToggleBuilder::class),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $object instanceof ContentEntityInterface
      ? $this->favoriteToggleBuilder->access($account ?? $this->currentUser, $object, $object->language()->getId())
      : AccessResult::forbidden();

    return $return_as_object ? $result : $result->isAllowed();
  }

}
