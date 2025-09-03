<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Textfield;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
    $translation_context = 'Location autocomplete';

    $element['#theme'] = 'helfi_location_autocomplete';
    $element['#attributes']['data-helfi-location-autocomplete'] = TRUE;
    $element['#attached']['library'][] = 'helfi_platform_config/location_autocomplete';
    $element['#attached']['drupalSettings'] = [
      'helsinki_near_you_form' => [
        'minCharAssistiveHint' => new TranslatableMarkup('Type @count or more characters for results', [], ['context' => $translation_context]),
        'inputAssistiveHint' => new TranslatableMarkup(
          'When autocomplete results are available use up and down arrows to review and enter to select. Touch device users, explore by touch or with swipe gestures.',
          [],
          ['context' => $translation_context]
        ),
        'noResultsAssistiveHint' => new TranslatableMarkup('No address suggestions were found', [], ['context' => $translation_context]),
        'someResultsAssistiveHint' => new TranslatableMarkup('There are @count results available.', [], ['context' => $translation_context]),
        'oneResultAssistiveHint' => new TranslatableMarkup('There is one result available.', [], ['context' => $translation_context]),
        'highlightedAssistiveHint' => new TranslatableMarkup(
          '@selectedItem @position of @count is highlighted',
          [],
          ['context' => $translation_context]
        ),
      ],
    ];

    // Remove "form-autocomplete" class.
    // This prevents Drupal autocomplete from hijacking the element.
    $element['#attributes']['class'] = array_filter(
      $element['#attributes']['class'] ?? [],
      static fn ($class) => $class !== 'form-autocomplete'
    );

    return $element;
  }

}
