<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\media_library\Form\AddFormBase;

/**
 * {@inheritDoc}
 */
class HelfiChartAddForm extends AddFormBase {

  /**
   * {@inheritDoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state) {
    $container = [
      '#type' => 'container',
    ];
    $container['helfi_chart_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chart embed URL'),
      '#description' => $this->t('Enter the chart embed URL from @powerbi.', [
        '@powerbi' => Link::fromTextAndUrl('https://app.powerbi.com/', Url::fromUri('https://app.powerbi.com/', ['attributes' => ['target' => '_blank']]))->toString(),
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
  public function addButtonSubmit(array $form, FormStateInterface $form_state) {
    $this->processInputValues([$form_state->getValue('helfi_chart_url')], $form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'helfi_chart_add_form';
  }

}
