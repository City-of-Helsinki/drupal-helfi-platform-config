<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\views\filter;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\join\JoinPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter nodes by authorship relation to the current user.
 */
#[ViewsFilter('helfi_node_authorship')]
class NodeAuthorshipFilter extends FilterPluginBase {

  /**
   * Disables the operator field; property name is required by FilterPluginBase.
   *
   * @var bool
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $no_operator = TRUE;

  /**
   * The views query object typed as SQL for method availability.
   *
   * @var \Drupal\views\Plugin\views\query\Sql
   */
  public $query;

  /**
   * Constructs a NodeAuthorshipFilter object.
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
   *
   * @return array<string, mixed>
   *   The options array with authorship filter defaults.
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['value']['default'] = 'either';
    $options['expose']['contains']['required']['default'] = TRUE;
    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function valueForm(mixed &$form, FormStateInterface $form_state): void {
    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Show content', [], ['context' => 'Node authorship filter']),
      '#options' => $this->valueOptions(),
      '#default_value' => $this->value ?: 'either',
    ];
  }

  /**
   * Returns the available filter value options.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Keyed by value, labelled options.
   */
  protected function valueOptions(): array {
    return [
      'either' => $this->t('Authored or last edited', [], ['context' => 'Node authorship filter']),
      'authored' => $this->t('Authored', [], ['context' => 'Node authorship filter']),
      'edited' => $this->t('Last edited', [], ['context' => 'Node authorship filter']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return (string) ($this->valueOptions()[$this->value] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $uid = (string) $this->currentUser->id();
    $value = is_array($this->value) ? reset($this->value) : $this->value;
    if (!in_array($value, ['authored', 'edited', 'either'])) {
      $value = 'either';
    }

    $group = (string) $this->options['group'];
    if ($value === 'authored') {
      $this->query->addWhere($group, 'node_field_data.uid', $uid);
      return;
    }

    $definition = [
      'table' => 'node_revision',
      'field' => 'vid',
      'left_table' => 'node_field_data',
      'left_field' => 'vid',
      'type' => 'LEFT',
    ];
    $join = $this->joinPluginManager->createInstance('standard', $definition);
    assert($join instanceof JoinPluginBase);
    $this->query->addRelationship('node_revision', $join, 'node_field_data');

    if ($value === 'edited') {
      $this->query->addWhere($group, 'node_revision.revision_uid', $uid);
      return;
    }

    // 'either': add both conditions in an OR group.
    $or_group = (string) $this->query->setWhereGroup('OR');
    $this->query->addWhere($or_group, 'node_field_data.uid', $uid);
    $this->query->addWhere($or_group, 'node_revision.revision_uid', $uid);
  }

}
