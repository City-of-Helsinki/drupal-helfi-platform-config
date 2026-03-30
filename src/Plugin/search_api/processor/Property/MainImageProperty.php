<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;

final class MainImageProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  public function defaultConfiguration(): array {
    return [
      'field_name' => '',
      'valid_node_types' => [],
    ];
  }

  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image field name'),
      '#default_value' => $this->configuration['field_name'],
    ];

    $form['valid_node_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Valid node types'),
      '#multiple' => TRUE,
      '#default_value' => $this->configuration['valid_node_types'],
    ];
    foreach (NodeType::loadMultiple() as $node_type) {
      $form['valid_node_types']['#options'][$node_type->id()] = $node_type->label();
    }

    return $form;
  }

}
