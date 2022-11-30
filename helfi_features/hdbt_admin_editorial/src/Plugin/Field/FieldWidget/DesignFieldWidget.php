<?php

namespace Drupal\hdbt_admin_editorial\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hdbt_admin_editorial\DesignSelectionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'design_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "design_field_widget",
 *   module = "hdbt_admin_editorial",
 *   label = @Translation("Design field widget"),
 *   field_types = {
 *     "list_string"
 *   },
 *   multiple_values = FALSE
 * )
 */
class DesignFieldWidget extends OptionsSelectWidget {

  /**
   * The design selection manager service.
   *
   * @var \Drupal\hdbt_admin_editorial\DesignSelectionManager
   */
  protected DesignSelectionManager $designSelectionManager;

  /**
   * {@inheritDoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, DesignSelectionManager $design_selection_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->designSelectionManager = $design_selection_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('hdbt_admin_editorial.design_selection_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#type'] = 'select2';
    $element['#cardinality'] = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
    $element['#select2'] = [
      'width' => $this->getSetting('width') ?? '400px',
    ];
    $element['#options'] = $this->getOptions($items->getEntity());
    $element['#default_value'] = $this->getSelectedOptions($items);
    $element['#attached']['library'][] = 'hdbt_admin_editorial/design_selection';
    $element['#attached']['drupalSettings']['designSelect']['images'] = $this->designSelectionManager->getImages(
      $this->getFieldName(),
      array_keys($this->getOptions($items->getEntity()))
    );
    $element['#attributes']['class'][] = 'design-selection';
    $element['#attributes']['data-design-select'] = $this->getFieldName();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {

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
  protected function getEmptyLabel() {}

  /**
   * Get field name and convert it to a more suitable name for our needs.
   *
   * @return string|string[]
   *   Returns field name.
   */
  protected function getFieldName() {
    $field_name = $this->fieldDefinition->getName();
    if ($field_name) {
      $name = str_replace('field_', '', $field_name);
      return str_replace('_', '-', $name);
    }
    return $this->fieldDefinition->getName();
  }

}
