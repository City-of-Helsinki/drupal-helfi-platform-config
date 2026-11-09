<?php

declare(strict_types=1);

namespace Drupal\helfi_media_chart\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\helfi_media_chart\Plugin\media\Source\Chart;
use Drupal\helfi_media_chart\Plugin\Validation\Constraint\ValidHelfiChartLinkConstraintValidator;
use Drupal\helfi_media_chart\UrlParserTrait;
use Drupal\media_library\Form\AddFormBase;

/**
 * {@inheritDoc}
 */
class HelfiChartAddForm extends AddFormBase {

  use UrlParserTrait;

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
      '#element_validate' => ['::validateMediaChartUrl'],
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
   * Validate the chart url before creating the media entity.
   *
   * @param array $element
   *   The element.
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function validateMediaChartUrl(array &$element, FormStateInterface $form_state): void {
    $uri = trim((string) $element['#value']);
    try {
      $this->mediaUrlToUri($uri);
    }
    catch (\Exception $e) {
      $form_state->setError($element, $this->t('Given host (%value) is not valid, must be one of: %domains', [
        '%value' => $uri,
        '%domains' => implode(', ', Chart::CHART_POWERBI_URL),
      ]));
    }
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
    return 'media_library_add_form_helfi_chart';
  }

}
