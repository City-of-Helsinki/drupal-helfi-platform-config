<?php

declare(strict_types=1);

namespace Drupal\helfi_node_announcement\Hook;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Field widget hook implementations.
 */
class FieldWidgetHooks {

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * @param array<string, mixed> $element
   *   The widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $context
   *   The widget context.
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $widget = $context['widget'] ?? NULL;
    $items = $context['items'] ?? NULL;

    if (
      !$widget instanceof WidgetInterface ||
      $widget->getPluginId() !== 'select2_entity_reference' ||
      !$items instanceof FieldItemListInterface ||
      $items->getEntity()->bundle() !== 'announcement'
    ) {
      return;
    }

    // Remove the "Drag to re-order" text.
    if (
      is_array($element['#description']) &&
      ($element['#description']['#theme'] ?? NULL) === 'item_list'
    ) {
      $element['#description'] = $element['#description']['#items'][0] ?? '';
    }
  }

}
