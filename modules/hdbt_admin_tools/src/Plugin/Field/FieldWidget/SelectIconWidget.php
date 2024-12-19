<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Config\Config;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\hdbt_admin_tools\Plugin\Field\FieldType\SelectIcon;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'select_icon_widget' widget.
 *
 * @FieldWidget(
 *   id = "select_icon_widget",
 *   label = @Translation("Select icon"),
 *   field_types = {
 *     "select_icon"
 *   }
 * )
 */
final class SelectIconWidget extends WidgetBase {

  /**
   * Contains the hdbt_admin_tools.site_settings configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $selectIconConfig;

  /**
   * Contains the array of options for the widget.
   *
   * @var array
   */
  protected array $options;

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
   * @param \Drupal\Core\Config\Config $select_icon_config
   *   TomSelect icon configuration.
   */
  public function __construct(string $plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, Config $select_icon_config) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->selectIconConfig = $select_icon_config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory')->getEditable('hdbt_admin_tools.site_settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $options = ['' => $this->t('- None -')] + SelectIcon::loadIcons();
    $element['icon'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon'),
      '#cardinality' => $this->fieldDefinition->getFieldStorageDefinition()->getCardinality(),
      '#theme' => 'select_icon_widget',
      '#options' => $options,
      '#default_value' => $this->getSelectedOptions($items),
      '#attached' => [
        'library' => [
          'hdbt_admin_tools/select_icon',
        ],
      ],
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
      $this->options = SelectIcon::loadIcons();
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
