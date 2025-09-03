<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Textfield;

/**
 * Autocomplete element for location autocomplete.
 */
#[FormElement('helfi_location_autocomplete')]
class LocationAutocomplete extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    $class = static::class;
    $info['#process'][] = [$class, 'processLocationAutocomplete'];
    return $info;
  }

  /**
   * Preprocess callback.
   */
  public static function processLocationAutocomplete(array $element): array {
    $element['#theme'] = 'helfi_location_autocomplete';
    $element['#attributes']['data-helfi-location-autocomplete'] = TRUE;
    $element['#attached']['library'][] = 'helfi_platform_config/location_autocomplete';

    // Remove "form-autocomplete" class.
    // This prevents Drupal autocomplete from hijacking the element.
    $element['#attributes']['class'] = array_filter(
      $element['#attributes']['class'] ?? [],
      static fn ($class) => $class !== 'form-autocomplete'
    );

    return $element;
  }

}
