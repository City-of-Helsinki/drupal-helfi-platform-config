<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\helfi_users\FavoriteStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives favorite actions for each favorite entity type.
 */
final class FavoriteActionDeriver extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get(EntityTypeManagerInterface::class));
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, array<string, mixed>>
   *   The derivative definitions.
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    foreach (FavoriteStorage::ENTITY_TYPES as $entityTypeId) {
      if ($this->entityTypeManager->hasDefinition($entityTypeId)) {
        $this->derivatives[$entityTypeId] = ['type' => $entityTypeId] + $base_plugin_definition;
      }
    }
    return $this->derivatives;
  }

}
