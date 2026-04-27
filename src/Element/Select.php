<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Element;

use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Select as CoreSelect;

/**
 * Provides helfi select element.
 *
 * This element attempts to mimic the style and functionality of an HDS select
 * component without the need for React. Only the features from HDS that
 * are currently important for Drupal development are implemented.
 *
 * @see https://hds.hel.fi/components/select/
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
 */
#[FormElement('helfi_select')]
class Select extends CoreSelect {

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

}
