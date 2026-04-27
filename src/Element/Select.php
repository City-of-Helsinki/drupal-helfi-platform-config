<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Element;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Select as CoreSelect;
use Drupal\Core\Url;

/**
 * Provides helfi select element.
 *
 * This element attempts to mimic the functionality of an HDS select
 * component without the need for React. Only the features from HDS that
 * are currently important for Drupal development are implemented.
 *
 * @see https://hds.hel.fi/components/select/
 *
 *  Properties:
 *  - #autocomplete_options_callback: (optional) A callback to return all valid
 *    currently selected options. @see static::getValidSelectedOptions().
 *  - #autocomplete_route_callback: (optional) A callback that sets the
 *    #autocomplete_route_name and autocomplete_route_parameters keys on the
 *    render element. @see static::setAutocompleteRouteParameters().
 *
 * Usage example:
 * @code
 * $form['example_select'] = [
 *   '#type' => 'helfi_select',
 *   '#title' => $this->t('Select element'),
 *   '#options' => [
 *     '1' => $this->t('One'),
 *     '2' => $this->t('Two'),
 *   ],
 * ];
 * @endcode
 *
 * Autocomplete example:
 * @code
 * $form['example_select'] = [
 *   '#type' => 'helfi_select',
 *   '#title' => $this->t('Select element'),
 *   ... TODO
 * ];
 * @endcode
 */
#[FormElement('helfi_select')]
class Select extends CoreSelect {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    $info['#autocomplete'] = FALSE;
    $info['#pre_render'][] = [static::class, 'preRenderAutocomplete'];
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public static function preRenderSelect($element): array {
    $element = parent::preRenderSelect($element);

    $element['#attributes']['class'][] = 'helfi-select';
    $element['#attached']['library'][] = 'helfi_platform_config/helfi_select';

    return $element;
  }

  /**
   * Attach autocomplete behavior to the render element.
   */
  public static function preRenderAutocomplete(array $element): array {
    if (!$element['#autocomplete']) {
      return $element;
    }

    $value_callable = $element['#autocomplete_route_callback'] ?? NULL;
    if (!$value_callable || !is_callable($value_callable)) {
      $value_callable = Select::class . '::setAutocompleteRouteParameters';
    }
    $element = call_user_func_array($value_callable, [&$element]);

    // Reduce options to the preselected ones and bring them in the correct
    // order.
    $options = OptGroup::flattenOptions($element['#options']);
    $values = $element['#value'] ?? $element['#default_value'];
    $values = is_array($values) ? $values : [$values];
    $element['#options'] = [];
    foreach ($values as $value) {
      if (isset($options[$value])) {
        $element['#options'][$value] = $options[$value];
      }
    }

    $access = \Drupal::service(AccessManagerInterface::class)
      ->checkNamedRoute(
        $element['#autocomplete_route_name'],
        $element['#autocomplete_route_parameters'],
        \Drupal::currentUser(),
        TRUE
      );

    if ($access->isAllowed()) {
      $url = Url::fromRoute($element['#autocomplete_route_name'], $element['#autocomplete_route_parameters'])
        ->toString(TRUE);

      // Provide a data attribute for the JavaScript behavior to bind to.
      $element['#attributes']['data-select2-config'] += [
        'url' => $url->getGeneratedUrl(),
      ];

      // Apply bubbleable metadata to the element.
      $url->applyTo($element);
    }

    return $element;
  }

  /**
   * Sets the autocomplete route parameters.
   *
   * @param array $element
   *   The render element.
   *
   * @return array
   *   The render element with autocomplete route parameters.
   */
  protected static function setAutocompleteRouteParameters(array &$element): array {
    $complete_form = [];
    $element = EntityAutocomplete::processEntityAutocomplete($element, new FormState(), $complete_form);
    $element['#autocomplete_route_name'] = 'select2.entity_autocomplete';
    return $element;
  }

}
