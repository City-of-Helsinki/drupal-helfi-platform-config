<?php

declare(strict_types=1);

namespace Drupal\hdbt_admin_tools\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Trait for handling character counter settings and form elements.
 */
trait CharacterCounterFieldWidgetTrait {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'counter_step' => 160,
      'counter_total' => 200,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    $element['counter_step'] = [
      '#type' => 'number',
      '#title' => $this->t('Suggestion text character count'),
      '#default_value' => $this->getSetting('counter_step'),
      '#required' => TRUE,
    ];
    $element['counter_total'] = [
      '#type' => 'number',
      '#title' => $this->t('Warning text character count'),
      '#default_value' => $this->getSetting('counter_total'),
      '#required' => TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Suggestion text character count: @count', ['@count' => $this->getSetting('counter_step')]);
    $summary[] = $this->t('Warning text character count: @count', ['@count' => $this->getSetting('counter_total')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['value']['#character_counter'] = TRUE;
    $element['value']['#counter_step'] = $this->getSetting('counter_step');
    $element['value']['#counter_total'] = $this->getSetting('counter_total');
    return $element;
  }

}
