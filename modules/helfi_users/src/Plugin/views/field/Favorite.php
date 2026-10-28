<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\views\field;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_users\FavoriteToggleBuilder;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the favorite toggle and sorts rows by favorite.
 */
#[ViewsField('helfi_favorite')]
final class Favorite extends FieldPluginBase {

  /**
   * The views query object typed as SQL for method availability.
   *
   * @var \Drupal\views\Plugin\views\query\Sql
   */
  public $query;

  /**
   * Constructs a Favorite object.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $joinPluginManager
   *   The views join plugin manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    private readonly AccountInterface $currentUser,
    private readonly PluginManagerInterface $joinPluginManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    /** @var static $instance */
    $instance = new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('plugin.manager.views.join'),
    );
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->addAdditionalFields(['langcode' => $this->definition['langcode_field']]);
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order): void {
    $tableAlias = $this->ensureMyTable();

    // Join the current user's favorites.
    $definition = [
      'table' => 'helfi_users_favorite',
      'field' => 'entity_id',
      'left_table' => $tableAlias,
      'left_field' => $this->definition['id_field'],
      'type' => 'LEFT',
      'extra' => [
        [
          'field' => 'entity_type',
          'value' => $this->definition['entity_type'],
        ],
        [
          'field' => 'uid',
          'value' => $this->currentUser->id(),
        ],
        [
          'field' => 'langcode',
          'left_field' => $this->definition['langcode_field'],
        ],
      ],
    ];
    $join = $this->joinPluginManager->createInstance('standard', $definition);
    assert($join instanceof JoinPluginBase);
    $favoriteAlias = $this->query->addRelationship('helfi_users_favorite', $join, $tableAlias);

    // Sort favorites first in ascending order.
    $this->query->addOrderBy(NULL, "$favoriteAlias.uid IS NULL", $order, $tableAlias . '_helfi_favorite');

    // Disable results caching for favorite sorting.
    $this->displayHandler->setOption('cache', ['type' => 'none', 'options' => []]);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The favorite toggle placeholder.
   */
  public function render(ResultRow $values): array {
    $entity = $this->getEntity($values);
    if (!$entity) {
      return [];
    }

    return [
      '#lazy_builder' => [
        FavoriteToggleBuilder::class . ':lazyBuild',
        [
          $entity->getEntityTypeId(),
          (string) $entity->id(),
          (string) $this->getValue($values, 'langcode'),
          'icon',
        ],
      ],
      '#create_placeholder' => TRUE,
    ];
  }

}
