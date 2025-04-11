<?php

declare(strict_types=1);

namespace Drupal\helfi_test_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Test content for form.
 */
class HelfiTestContentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'helfi_test_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text field'),
      '#description' => $this->t('A description for the text field.'),
    ];

    $form['textarea'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Textarea'),
      '#description' => $this->t('A description for the textarea.'),
    ];

    $form['select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select list'),
      '#options' => [
        'value_a' => $this->t('Option 1'),
        'value_b' => $this->t('Option 2'),
      ],
      '#description' => $this->t('A description for the textarea.'),
    ];

    $form['fieldset_radio'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select either one'),
      '#description' => $this->t('A description for the radio fieldset.'),
    ];
    $form['fieldset_radio']['fieldset_radios'] = [
      '#type' => 'radios',
      '#default_value' => 'one',
      '#options' => [
        'one' => $this->t('Selection 1'),
        'two' => $this->t('Selection 2'),
      ],
    ];

    $form['fieldset_checkbox'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select either one'),
      '#description' => $this->t('A description for the checkbox fieldset.'),
    ];

    $form['fieldset_checkbox']['fieldset_checkbox_not_checked'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Checkbox'),
      '#default_value' => FALSE,
    ];

    $form['fieldset_checkbox']['fieldset_checkbox_selected'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Selected checkbox'),
      '#default_value' => TRUE,
    ];

    $form['radios'] = [
      '#type' => 'radios',
      '#default_value' => 'two',
      '#options' => [
        'one' => $this->t('Selection 1'),
        'two' => $this->t('Selection 2'),
      ],
    ];

    $form['checkbox'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Checkbox'),
      '#default_value' => FALSE,
    ];

    $form['checkbox_selected'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Selected checkbox'),
      '#default_value' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['cancel'] = [
      '#type' => 'button',
      '#attributes' => [
        'class' => [
          'hds-button--secondary',
        ],
        'style' => 'margin-left: 16px;',
      ],
      '#value' => $this->t('Cancel'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
