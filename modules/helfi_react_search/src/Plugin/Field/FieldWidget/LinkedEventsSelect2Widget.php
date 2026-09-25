<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\Field\FieldWidget;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_react_search\DTO\LinkedEventsItem;
use Drupal\select2\Plugin\Field\FieldWidget\Select2Widget;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for linked events filters.
 */
#[FieldWidget(
  id: "linked_events_select2",
  label: new TranslatableMarkup('Helfi: Linked events select2 widget'),
  field_types: ['string'],
  multiple_values: TRUE,
)]
final class LinkedEventsSelect2Widget extends Select2Widget {

  use DependencySerializationTrait;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * The cache backend.
   */
  protected CacheBackendInterface $cache;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $widget->languageManager = $container->get(LanguageManagerInterface::class);
    $widget->httpClient = $container->get('http_client');
    $widget->cache = $container->get('cache.default');
    $widget->logger = $container->get('logger.factory')->get('helfi_react_search');
    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'endpoint' => 'keyword',
      'query' => '',
      'keyword_set' => '',
    ] + parent::defaultSettings();
  }

  /**
   * Select2 callback for getting an array of currently selected options.
   *
   * @param array $element
   *   The render element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Key => encoded item, Value => entity label.
   */
  public function getValidSelectedOptions(array $element, FormStateInterface $form_state): array {
    $value = is_array($element['#value']) ? $element['#value'] : [$element['#value']];

    $options = [];
    foreach ($value as $item) {
      $options[$item] = $this->getOptionLabel($item);
    }

    return $options;
  }

  /**
   * Select2 callback for settings the autocomplete route parameters.
   *
   * @param array $element
   *   The render element.
   *
   * @return array
   *   The render element with autocomplete route parameters.
   */
  public static function setAutocompleteRouteParameters(array &$element): array {
    $complete_form = [];
    $element = EntityAutocomplete::processEntityAutocomplete($element, new FormState(), $complete_form);
    $element['#autocomplete_route_name'] = 'helfi_react_search.linked_events.autocomplete';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#multiple'] = $this->multiple;

    if ($this->getSetting('keyword_set')) {
      return $element;
    }

    $element['#target_type'] = $this->getSetting('endpoint');
    $element['#autocomplete_route_callback'] = self::class . '::setAutocompleteRouteParameters';
    $element['#autocomplete_options_callback'] = [$this, 'getValidSelectedOptions'];
    $element['#selection_settings'] = [
      'query' => $this->getSetting('query'),
      'search_key' => $this->getSetting('search_key'),
    ];
    $element['#autocomplete'] = TRUE;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions(FieldableEntityInterface $entity): array {
    if (!isset($this->options)) {
      $selected_options = [];

      // Get all currently selected options.
      foreach ($entity->get($this->fieldDefinition->getName()) as $item) {
        if ($item->{$this->column} !== NULL) {
          $selected_options[$item->{$this->column}] = $this->getOptionLabel($item->{$this->column});
        }
      }

      $this->options = $selected_options;

      if ($keyword_set = $this->getSetting('keyword_set')) {
        $this->options = $this->getKeywordSetOptions($keyword_set, $selected_options);
      }
    }

    return $this->options;
  }

  /**
   * Get options from a Linked Events keyword set.
   *
   * @param string $keyword_set
   *   The keyword set id, e.g. 'helsinki:audiences'.
   * @param array<string, string> $selected_options
   *   Currently selected options. These are kept as they are, so that
   *   the stored values match the options even if the API data changes.
   *
   * @return array<string, string>
   *   Key => encoded item, Value => option label.
   */
  private function getKeywordSetOptions(string $keyword_set, array $selected_options): array {
    $selected_ids = [];
    foreach (array_keys($selected_options) as $value) {
      $selected_ids[json_decode($value)?->id] = TRUE;
    }

    $options = $selected_options;
    foreach ($this->getKeywordSetItems($keyword_set) as $item) {
      if (isset($selected_ids[$item->id])) {
        continue;
      }
      $value = (string) json_encode($item);
      $options[$value] = $this->getOptionLabel($value);
    }

    natcasesort($options);

    /** @var array<string, string> $options */
    return $options;
  }

  /**
   * Fetch keywords of a Linked Events keyword set.
   *
   * @param string $keyword_set
   *   The keyword set id.
   *
   * @return \Drupal\helfi_react_search\DTO\LinkedEventsItem[]
   *   The keywords.
   */
  private function getKeywordSetItems(string $keyword_set): array {
    $cid = 'helfi_react_search:keyword_set:' . $keyword_set;
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    try {
      $response = $this->httpClient->request('GET', 'https://api.hel.fi/linkedevents/v1/keyword_set/' . rawurlencode($keyword_set) . '/', [
        'query' => [
          'format' => 'json',
          'include' => 'keywords',
        ],
      ]);
      $response = json_decode(
        json: $response->getBody()->getContents(),
        flags: JSON_THROW_ON_ERROR,
      );
    }
    catch (GuzzleException | \JsonException $e) {
      $this->logger->error('Failed to fetch Linked Events keyword set @id: @message', [
        '@id' => $keyword_set,
        '@message' => $e->getMessage(),
      ]);
      return [];
    }

    $items = [];
    foreach ($response->keywords ?? [] as $keyword) {
      if (isset($keyword->id, $keyword->name)) {
        $items[] = new LinkedEventsItem($keyword->id, (array) $keyword->name);
      }
    }

    $this->cache->set($cid, $items, time() + 86400);

    return $items;
  }

  /**
   * Get option label from value.
   *
   * Option values are JSON serialized LinkedEventsItem objects.
   *
   * @param string $value
   *   Option element value.
   *
   * @return string
   *   Option element label.
   *
   * @see \Drupal\helfi_react_search\DTO\LinkedEventsItem
   */
  private function getOptionLabel(string $value): string {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // The linked events data is stored as JSON serialized
    // strings so that no API calls needs to made to get the
    // translated labels.
    // See LinkedEventsAutocompleteController.
    $json = json_decode($value);

    return $json->name?->{$langcode} ?: $json->name?->en ?: 'Unknown';
  }

}
