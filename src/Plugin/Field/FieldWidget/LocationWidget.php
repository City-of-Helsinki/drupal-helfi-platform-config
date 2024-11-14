<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Defines the 'location' field widget.
 */
#[FieldWidget(
  id: "location",
  label: new TranslatableMarkup('Location'),
  field_types: ['location']
)]
final class LocationWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['latitude'] = [
      '#type' => 'number',
      '#default_value' => $items[$delta]->latitude ?? NULL,
      '#step' => 'any',
    ];

    $element['longitude'] = [
      '#type' => 'number',
      '#default_value' => $items[$delta]->longitude ?? NULL,
      '#step' => 'any',
    ];

    $element['#theme_wrappers'] = ['container', 'form_element'];
    $element['#attributes']['class'][] = 'location-elements';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $error, array $form, FormStateInterface $form_state): array|bool {
    $element = parent::errorElement($element, $error, $form, $form_state);
    if ($element === FALSE) {
      return FALSE;
    }
    $error_property = explode('.', $error->getPropertyPath())[1];
    return $element[$error_property];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as $delta => $value) {
      if ($value['latitude'] === '' || $value['longitude'] === '') {
        $values[$delta]['latitude'] = NULL;
        $values[$delta]['longitude'] = NULL;
      }
    }
    return $values;
  }

}
