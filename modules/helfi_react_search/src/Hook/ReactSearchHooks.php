<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_react_search\Entity\EventList;

/**
 * ReactSearch hook-class.
 */
final class ReactSearchHooks {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Implements hook_entity_bundle_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    if (isset($bundles['paragraph']['event_list'])) {
      $bundles['paragraph']['event_list']['class'] = EventList::class;
    }
  }

  /**
   * Implements hook_preprocess_paragraph().
   *
   * Allow React search -paragraphs to request elasticsearch & sentry.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $reactParagraphs = [
      'school_search',
      'job_search',
      'district_and_project_search',
      'ploughing_schedule',
      'health_station_search',
      'maternity_and_child_health_clini',
      'event_list',
    ];

    $config = $this->configFactory->get('elastic_proxy.settings');
    $react_search_config = $this->configFactory->get('react_search.settings');

    if (
      isset($variables['paragraph']) &&
      in_array($variables['paragraph']->getType(), $reactParagraphs)
    ) {
      if ($proxyUrl = $config->get('elastic_proxy_url')) {
        $variables['#attached']['drupalSettings']['helfi_react_search']['elastic_proxy_url'] = $proxyUrl;
      }
      if ($sentry_dsn_react = $react_search_config->get('sentry_dsn_react')) {
        $variables['#attached']['drupalSettings']['helfi_react_search']['sentry_dsn_react'] = $sentry_dsn_react;
      }
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'debug_item__search_api' => [
        'variables' => [
          'id' => NULL,
          'label' => NULL,
          'data' => [],
        ],
        'template' => 'debug-item--search-api',
      ],
    ];
  }

  /**
   * Implements field_widget_single_element_paragraphs_form_alter().
   */
  #[Hook('field_widget_single_element_paragraphs_form_alter')]
  public function fieldWidgetSingleElementParagraphsFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    if (empty($element['#paragraph_type']) || $element['#paragraph_type'] != 'event_list') {
      return;
    }

    $select = fn (string $prefix) => ':input[name="' . $context['items']->getName() . '[' . $element['#delta'] . '][subform]' . $prefix . '"]';
    $states = [
      'field_event_list_category_event' => [
        'state' => 'invisible',
        'condition' => [$select('[field_event_list_type]') => ['value' => 'hobbies']],
      ],
      'field_event_list_category_hobby' => [
        'state' => 'invisible',
        'condition' => [$select('[field_event_list_type]') => ['value' => 'events']],
      ],
      'field_event_location' => [
        'state' => 'disabled',
        'condition' => [$select('[field_remote_events][value]') => ['checked' => TRUE]],
      ],
      'field_remote_events' => [
        'state' => 'disabled',
        'condition' => [$select('[field_event_location][value]') => ['checked' => TRUE]],
      ],
    ];

    foreach ($states as $field => ['state' => $state, 'condition' => $condition]) {
      $element['subform'][$field]['#states'] = [
        $state => [$condition],
      ];
    }
  }

  /**
   * Implements hook_preprocess_form_element().
   */
  #[Hook('preprocess_form_element')]
  public function preprocessFormElement(array &$variables): void {
    if (!isset($variables['name']) || !isset($variables['description'])) {
      return;
    }

    // Remove the field_api_url description bullet list item and use
    // just the first description set in configuration files.
    if (
      str_contains($variables['name'], 'field_api_url') &&
      isset($variables['description']['content']['#items'])
    ) {
      $variables['description']['content'] = reset($variables['description']['content']['#items']);
    }
  }

}
