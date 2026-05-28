<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

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
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['value']['default'] = 'either';
    $options['expose']['contains']['required']['default'] = TRUE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
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
    $uid = \Drupal::currentUser()->id();
    $value = is_array($this->value) ? reset($this->value) : $this->value;
    if (!in_array($value, ['authored', 'edited', 'either'])) {
      $value = 'either';
    }

    if ($value === 'authored') {
      $this->query->addWhere($this->options['group'], 'node_field_data.uid', $uid);
      return;
    }

    $definition = [
      'table' => 'node_revision',
      'field' => 'vid',
      'left_table' => 'node_field_data',
      'left_field' => 'vid',
      'type' => 'LEFT',
    ];
    $join = \Drupal::service('plugin.manager.views.join')
      ->createInstance('standard', $definition);
    $this->query->addRelationship('node_revision', $join, 'node_field_data');

    if ($value === 'edited') {
      $this->query->addWhere($this->options['group'], 'node_revision.revision_uid', $uid);
      return;
    }

    // 'either': add both conditions in an OR group.
    $or_group = $this->query->setWhereGroup('OR');
    $this->query->addWhere($or_group, 'node_field_data.uid', $uid);
    $this->query->addWhere($or_group, 'node_revision.revision_uid', $uid);
  }

}
