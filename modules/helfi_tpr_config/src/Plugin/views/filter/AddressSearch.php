<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\helfi_address_search\Plugin\views\filter\AddressSearch as AddressSearchBase;

/**
 * Alters address search plugin to use Helfi location autocomplete.
 */
class AddressSearch extends AddressSearchBase {

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    parent::valueForm($form, $form_state);

    $form['value']['#type'] = 'helfi_location_autocomplete';
  }

}
