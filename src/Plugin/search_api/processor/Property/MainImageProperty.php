<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;

/**
 * A search-api property for main image.
 */
final class MainImageProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name' => NULL,
      'image_styles' => [
        ['id' => '1.5_304w_203h', 'breakpoint' => '1248'],
        ['id' => '1.5_294w_196h', 'breakpoint' => '992'],
        ['id' => '1.5_220w_147h', 'breakpoint' => '768'],
        ['id' => '1.5_176w_118h', 'breakpoint' => '576'],
        ['id' => '1.5_511w_341h', 'breakpoint' => '320'],
        ['id' => '1.5_608w_406w_LQ', 'breakpoint' => '1248_2x'],
        ['id' => '1.5_588w_392h_LQ', 'breakpoint' => '992_2x'],
        ['id' => '1.5_440w_294h_LQ', 'breakpoint' => '768_2x'],
        ['id' => '1.5_352w_236h_LQ', 'breakpoint' => '576_2x'],
        ['id' => '1.5_1022w_682h_LQ', 'breakpoint' => '320_2x'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image field name'),
      '#default_value' => $this->configuration['field_name'],
    ];
    return $form;
  }

}
