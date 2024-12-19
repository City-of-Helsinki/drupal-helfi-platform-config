<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for select widgets.
 */
abstract class SelectWidgetBase extends OptionsSelectWidget {

  /**
   * The design selection manager service.
   *
   * @var \Drupal\hdbt_admin_tools\DesignSelectionManager
   */
  protected DesignSelectionManager $designSelectionManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    DesignSelectionManager $design_selection_manager,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings
    );
    $this->designSelectionManager = $design_selection_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('hdbt_admin_tools.design_selection_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#type'] = 'select';
    $element['#cardinality'] = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $element['#options'] = $this->getOptions($items->getEntity());
    $element['#default_value'] = $this->getSelectedOptions($items);
    $element['#theme'] = 'selection_widget';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(
    array $element,
    FormStateInterface $form_state,
  ): void {

    // Massage submitted form values.
    // Drupal\Core\Field\WidgetBase::submit() expects values as
    // an array of values keyed by delta first, then by column, while our
    // widgets return the opposite.
    if (is_array($element['#value'])) {
      $values = array_values($element['#value']);
    }
    else {
      $values = [$element['#value']];
    }

    // Filter out the '' option. Use a strict comparison, because
    // 0 == 'any string'.
    $index = array_search('', $values, TRUE);
    if ($index !== FALSE) {
      unset($values[$index]);
    }

    // Design field widget cannot handle multiple values.
    $item[$element['#key_column']] = $element['#value'];
    $form_state->setValueForElement($element, $item);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel(): ?string {
    return '';
  }

  /**
   * Get field name and convert it to a more suitable name for our needs.
   *
   * @return string
   *   Returns field name.
   */
  protected function getFieldName(): string {
    $field_name = $this->fieldDefinition->getName();
    if ($field_name) {
      $name = str_replace('field_', '', $field_name);
      $name = str_contains($name, 'link_design') ? 'link_design' : $name;
      return str_replace('_', '-', $name);
    }
    return $this->fieldDefinition->getName();
  }

}
