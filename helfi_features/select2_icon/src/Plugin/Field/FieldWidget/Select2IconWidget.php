<?php

namespace Drupal\select2_icon\Plugin\Field\FieldWidget;

use Drupal\Core\Config\Config;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\select2_icon\Plugin\Field\FieldType\Select2Icon;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'select2_icon_widget' widget.
 *
 * @FieldWidget(
 *   id = "select2_icon_widget",
 *   label = @Translation("Select2 Icon"),
 *   field_types = {
 *     "select2_icon"
 *   }
 * )
 */
class Select2IconWidget extends WidgetBase {

  /**
   * Contains the select2_icon.settings configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $select2IconConfig;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Config\Config $select2_icon_config
   *   Select2 icon configuration.
   */
  public function __construct(string $plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, Config $select2_icon_config) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->select2IconConfig = $select2_icon_config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory')->getEditable('select2_icon.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['icon'] = [
      '#type' => 'select2',
      '#title' => $this->t('Icon'),
      '#cardinality' => $this->fieldDefinition->getFieldStorageDefinition()->getCardinality(),
      '#select2' => [
        'width' => $this->getSetting('width') ?? '400px',
      ],
      '#theme' => 'select2_icon_widget',
      '#options' => Select2Icon::loadIcons(),
      '#default_value' => $this->getSelectedOptions($items),
    ];

    return $element;
  }

  /**
   * Returns the array of options for the widget.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity for which to return options.
   *
   * @return array
   *   The array of options for the widget.
   */
  protected function getOptions(FieldableEntityInterface $entity): array {
    if (!isset($this->options)) {
      $this->options = Select2Icon::loadIcons();
    }
    return $this->options;
  }

  /**
   * Determines selected options from the incoming field values.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values.
   *
   * @return array
   *   The array of corresponding selected options.
   */
  protected function getSelectedOptions(FieldItemListInterface $items): array {
    // In case this widget is used for opt groups.
    $flat_options = OptGroup::flattenOptions($this->getOptions($items->getEntity()));

    $selected_options = [];
    foreach ($items as $item) {
      $value = $item->icon;
      if (isset($flat_options[$value])) {
        $selected_options[] = $value;
      }
    }
    return $selected_options;
  }

}
