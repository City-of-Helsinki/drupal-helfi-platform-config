<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Element;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Element\Select as CoreSelect;
use Drupal\Core\Url;

/**
 * Provides helfi select element.
 *
 * This element attempts to mimic the style and functionality of an HDS select
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
 * $form['example_autocomplete_select'] = [
 *   '#type' => 'helfi_select',
 *   '#title' => $this->t('Select element'),
 *   '#autocomplete_route_name' => 'helfi_api_base.location_autocomplete',
 * ];
 * @endcode
 */
#[FormElement('helfi_select')]
class Select extends CoreSelect {

  /**
   * {@inheritdoc}
   *
   * @phpstan-return array<string, mixed>
   */
  public function getInfo(): array {
    $info = parent::getInfo();

    $info['#process'][] = [static::class, 'processSelectAutocomplete'];

    return $info;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $element
   *    The render element.
   *
   * @return array<string, mixed>
   *    The processed render element.
   */
  #[\Override]
  public static function preRenderSelect($element): array {
    $element = parent::preRenderSelect($element);

    if (isset($element['#placeholder'])) {
      $element['#attributes']['placeholder'] = $element['#placeholder'];
    }

    $element['#attributes']['class'][] = 'helfi-select';
    $element['#attached']['library'][] = 'helfi_platform_config/helfi_select';

    return $element;
  }

  /**
   * Adds autocomplete functionality to elements.
   *
   * This sets up autocomplete functionality for elements with an
   * #autocomplete_route_name property, using the #autocomplete_route_parameters
   * and #autocomplete_query_parameters properties if present.
   *
   * @phpstan-param array<string, mixed> $element
   * @phpstan-param array<string, mixed> $complete_form
   * @phpstan-return array<string, mixed>
   */
  public static function processSelectAutocomplete(&$element, FormStateInterface $form_state, &$complete_form): array {
    $url = NULL;
    $access = FALSE;

    if (!empty($element['#autocomplete_route_name'])) {
      $parameters = $element['#autocomplete_route_parameters'] ?? [];
      $options = [];
      if (!empty($element['#autocomplete_query_parameters'])) {
        $options['query'] = $element['#autocomplete_query_parameters'];
      }
      $url = Url::fromRoute($element['#autocomplete_route_name'], $parameters, $options)->toString(TRUE);
      $access = \Drupal::service(AccessManagerInterface::class)
        ->checkNamedRoute($element['#autocomplete_route_name'], $parameters, \Drupal::currentUser(), TRUE);
    }

    if ($access) {
      $metadata = BubbleableMetadata::createFromRenderArray($element);
      if ($access->isAllowed()) {
        $element['#attributes']['class'][] = 'form-autocomplete';
        // Provide a data attribute for the JavaScript behavior to bind to.
        $element['#attributes']['data-autocomplete-path'] = $url->getGeneratedUrl();
        $metadata = $metadata->merge($url);
      }
      $metadata
        ->merge(BubbleableMetadata::createFromObject($access))
        ->applyTo($element);
    }

    return $element;
  }

}
