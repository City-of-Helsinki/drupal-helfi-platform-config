<?php

declare(strict_types = 1);

namespace Drupal\helfi_media_map\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\media_library\Form\AddFormBase;

/**
 * {@inheritDoc}
 */
class HelfiMediaMapAddForm extends AddFormBase {

  /**
   * {@inheritDoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state) : array {
    $container = [
      '#type' => 'container',
    ];
    $container['helfi_media_map_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map embed URL'),
      '#description' => $this->t('Enter the map embed URL from @kartta or @palvelukartta.', [
        '@kartta' => Link::fromTextAndUrl('https://kartta.hel.fi/', Url::fromUri('https://kartta.hel.fi/', ['attributes' => ['target' => '_blank']]))->toString(),
        '@palvelukartta' => Link::fromTextAndUrl('https://palvelukartta.hel.fi/fi/', Url::fromUri('https://palvelukartta.hel.fi/fi/', ['attributes' => ['target' => '_blank']]))->toString(),
      ]),
      '#maxlength' => 2048,
    ];

    $container['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#button_type' => 'primary',
      '#submit' => ['::addButtonSubmit'],
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-wrapper',
        // @todo Remove when https://www.drupal.org/project/drupal/issues/2504115 is fixed.
        'url' => Url::fromRoute('media_library.ui'),
        'options' => [
          'query' => $this->getMediaLibraryState($form_state)->all() + [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    $form['container'] = $container;

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function addButtonSubmit(array $form, FormStateInterface $form_state) : void {
    $this->processInputValues([$form_state->getValue('helfi_media_map_url')], $form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() : string {
    return 'helfi_media_map_add_form';
  }

}
