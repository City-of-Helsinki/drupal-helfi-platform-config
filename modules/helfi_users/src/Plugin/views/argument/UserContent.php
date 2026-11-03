<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\views\argument;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters entities last edited or marked as favorite by the user.
 */
#[ViewsArgument('helfi_user_content')]
final class UserContent extends NumericArgument {

  /**
   * The views query object typed as SQL for method availability.
   *
   * @var \Drupal\views\Plugin\views\query\Sql
   */
  public $query;

  /**
   * Constructs a UserContent object.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly AccountInterface $currentUser,
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
    );
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $arg
   *   The argument value.
   */
  public function validateArgument($arg): bool {
    // Allow only the current user's content.
    if ((string) $arg !== (string) $this->currentUser->id()) {
      return $this->argument_validated = FALSE;
    }
    return (bool) parent::validateArgument($arg);
  }

  /**
   * {@inheritdoc}
   *
   * @param bool $group_by
   *   Whether the query uses aggregation.
   */
  public function query($group_by = FALSE): void {
    $this->ensureMyTable();

    $alias = $this->tableAlias;
    $revisionField = $this->definition['revision_field'];
    $editorPlaceholder = $this->placeholder();
    $favoritePlaceholder = $this->placeholder();
    $entityTypePlaceholder = $this->placeholder();

    // Match content last edited or marked as favorite by the user.
    $edited = "$alias.$revisionField IN (SELECT r.$revisionField FROM {" . $this->definition['revision_table'] . "} r WHERE r." . $this->definition['revision_user_field'] . " = $editorPlaceholder)";
    $favorite = "EXISTS (SELECT 1 FROM {helfi_users_favorite} f WHERE f.uid = $favoritePlaceholder AND f.entity_type = $entityTypePlaceholder AND f.entity_id = $alias." . $this->definition['id_field'] . " AND f.langcode = $alias." . $this->definition['langcode_field'] . ')';

    $this->query->addWhereExpression('0', "($edited OR $favorite)", [
      $editorPlaceholder => (int) $this->argument,
      $favoritePlaceholder => (int) $this->argument,
      $entityTypePlaceholder => $this->definition['entity_type'],
    ]);

    // Disable results caching for favorite changes.
    $this->displayHandler->setOption('cache', ['type' => 'none', 'options' => []]);
  }

}
