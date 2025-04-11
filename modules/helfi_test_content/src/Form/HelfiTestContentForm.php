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
    $context = ['context' => 'Helfi test content'];
    $form['textfield'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text field', options: $context),
      '#description' => $this->t('A description for the text field.', options: $context),
    ];

    $form['textarea'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Textarea', options: $context),
      '#description' => $this->t('A description for the textarea.', options: $context),
    ];

    $form['select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select list', options: $context),
      '#options' => [
        'value_a' => $this->t('Option 1', options: $context),
        'value_b' => $this->t('Option 2', options: $context),
      ],
      '#description' => $this->t('A description for the textarea.', options: $context),
    ];

    $form['fieldset_radio'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select either one', options: $context),
      '#description' => $this->t('A description for the radio fieldset.', options: $context),
    ];
    $form['fieldset_radio']['fieldset_radios'] = [
      '#type' => 'radios',
      '#default_value' => 'one',
      '#options' => [
        'one' => $this->t('Selection 1', options: $context),
        'two' => $this->t('Selection 2', options: $context),
      ],
    ];

    $form['fieldset_checkbox'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select either one', options: $context),
      '#description' => $this->t('A description for the checkbox fieldset.', options: $context),
    ];

    $form['fieldset_checkbox']['fieldset_checkbox_not_checked'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Checkbox', options: $context),
      '#default_value' => FALSE,
    ];

    $form['fieldset_checkbox']['fieldset_checkbox_selected'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Selected checkbox', options: $context),
      '#default_value' => TRUE,
    ];

    $form['radios'] = [
      '#type' => 'radios',
      '#default_value' => 'two',
      '#options' => [
        'one' => $this->t('Selection 1', options: $context),
        'two' => $this->t('Selection 2', options: $context),
      ],
    ];

    $form['checkbox'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Checkbox', options: $context),
      '#default_value' => FALSE,
    ];

    $form['checkbox_selected'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Selected checkbox', options: $context),
      '#default_value' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit', options: $context),
    ];

    $form['cancel'] = [
      '#type' => 'button',
      '#attributes' => [
        'class' => [
          'hds-button--secondary',
        ],
        'style' => 'margin-left: 16px;',
      ],
      '#value' => $this->t('Cancel', options: $context),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
