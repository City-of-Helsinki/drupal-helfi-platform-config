<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Entity;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\helfi_react_search\DTO\LinkedEventsItem;
use Drupal\helfi_react_search\Enum\CourseCategory;
use Drupal\helfi_react_search\Enum\EventCategory;
use Drupal\helfi_react_search\Enum\EventListCategoryInterface;
use Drupal\helfi_react_search\Enum\Filters;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Bundle class for Hero -paragraph.
 */
class EventList extends Paragraph implements ParagraphInterface {

  /**
   * Get paragraph title.
   */
  public function getTitle(): ?string {
    return $this->get('field_event_list_title')->value;
  }

  /**
   * Get number of items to show.
   */
  public function getCount(): int {
    $default_value = 3;

    return (int) ($this->get('field_event_count')->value ?? $default_value);
  }

  /**
   * Get enabled types.
   */
  public function getEventListType(): ?string {
    return $this->get('field_event_list_type')->value;
  }

  /**
   * Get event categories.
   *
   * Caller must check if courses should be enabled with getEventListType().
   *
   * @return \Drupal\helfi_react_search\Enum\EventCategory[]
   *   Event categories.
   */
  public function getEventCategories(): array {
    $categories = [];

    foreach ($this->get('field_event_list_category_event') as $value) {
      $categories[] = EventCategory::tryFrom($value->getString());
    }

    return array_filter($categories);
  }

  /**
   * Get hobby categories.
   *
   * Caller must check if hobbies should be enabled with getEventListType().
   *
   * @return \Drupal\helfi_react_search\Enum\CourseCategory[]
   *   Hobby categories.
   */
  public function getHobbyCategories(): array {
    $categories = [];

    foreach ($this->get('field_event_list_category_hobby') as $value) {
      $categories[] = CourseCategory::tryFrom($value->getString());
    }

    return array_filter($categories);
  }

  /**
   * Get public URL to tapahtumat.hel.fi.
   *
   * Caller must check if courses should be enabled with getEventListType().
   */
  public function getEventsPublicUrl(): string {
    return $this->getPublicUrl('https://tapahtumat.hel.fi/fi/haku', $this->getEventCategories());
  }

  /**
   * Get public URL to harrastukset.hel.fi.
   *
   * Caller must check if courses should be enabled with getEventListType().
   */
  public function getHobbiesPublicUrl(): string {
    return $this->getPublicUrl('https://harrastukset.hel.fi/fi/haku', $this->getHobbyCategories());
  }

  /**
   * Build public url.
   *
   * @param string $baseUrl
   *   Base url.
   * @param \Drupal\helfi_react_search\Enum\EventListCategoryInterface[] $categories
   *   Selected category filters.
   */
  protected function getPublicUrl(string $baseUrl, array $categories): string {
    $query = [];

    $categories = array_map(static fn (EventListCategoryInterface $category) => $category->value, $categories);
    if ($categories) {
      $query['categories'] = implode(',', $categories);
    }

    $keywords = array_map(static fn (LinkedEventsItem $item) => $item->id, $this->getKeywords());
    if ($keywords) {
      $query['keyword'] = implode(',', $keywords);
    }

    $places = array_map(static fn (LinkedEventsItem $item) => $item->id, $this->getPlaces());
    if ($places) {
      $query['places'] = implode(',', $places);
    }

    if ($freeText = $this->get('field_event_list_free_text')->value) {
      // At the moment, some valid queries cannot be represented with the
      // paragraph form, so this offers an escape hatch for more advanced
      // filters.
      if (str_starts_with($freeText, '?')) {
        parse_str(substr($freeText, 1), $parsed);
        $query = array_merge($query, $parsed);
      }
      else {
        $query['text'] = $freeText;
      }
    }

    return Url::fromUri($baseUrl, [
      'query' => $query ,
    ])->toString();
  }

  /**
   * Build public url.
   *
   * @param array $options
   *   Filters as key = value array.
   *
   * @return string
   *   Resulting api url with params a query string
   */
  public function getApiUrl(array $options = []): string {
    $categories = match ($this->getEventListType()) {
      'events_and_hobbies' => array_merge($this->getEventCategories(), $this->getHobbyCategories()),
      'hobbies' => $this->getHobbyCategories(),
      'events', NULL => $this->getEventCategories(),
    };

    $keywords = array_map(static fn (LinkedEventsItem $item) => $item->id, $this->getKeywords());
    $places = array_map(static fn (LinkedEventsItem $item) => $item->id, $this->getPlaces());

    // Transform categories to an array of keywords for the API.
    foreach ($categories as $category) {
      $keywords = array_merge($keywords, $category->keywords());
    }

    $query = [
      'keyword' => implode(',', $keywords),
      'location' => implode(',', $places),
      'event_type' => match($this->getEventListType()) {
        // Linked events does not display courses by default.
        'events_and_hobbies' => 'General,Course',
        'hobbies' => 'Course',
        default => 'General',
      },
      'format' => 'json',
      'include' => 'keywords,location',
      'page' => 1,
      'page_size' => $this->getCount(),
      'sort' => 'end_time',
      'start' => 'now',
      'super_event_type' => 'umbrella,none',
      'language' => $this->language()->getId(),
    ];

    if ($freeText = $this->get('field_event_list_free_text')->value) {
      // At the moment, some valid queries cannot be represented with the
      // paragraph form, so this offers an escape hatch for more advanced
      // filters.
      if (str_starts_with($freeText, '?')) {
        parse_str(substr($freeText, 1), $parsed);
        $query = array_merge($query, $parsed);
      }
      else {
        $query['all_ongoing_AND'] = $freeText;
      }
    }

    $query = array_merge($query, $options);

    if (!isset($options['all_ongoing_AND'])) {
      $query['all_ongoing'] = 'true';
    }

    return Url::fromUri('https://api.hel.fi/linkedevents/v1/event/', [
      'query' => $query ,
    ])->toString();
  }

  /**
   * Get list of enabled places.
   *
   * @return \Drupal\helfi_react_search\DTO\LinkedEventsItem[]
   *   Enabled places.
   */
  public function getPlaces(): array {
    return $this->deserializeAutocompleteField($this->get('field_event_list_place'));
  }

  /**
   * Get list of enabled keywords.
   *
   * @return \Drupal\helfi_react_search\DTO\LinkedEventsItem[]
   *   Enabled places.
   */
  public function getKeywords(): array {
    return $this->deserializeAutocompleteField($this->get('field_event_list_keywords'));
  }

  /**
   * Get list of enabled filter keywords.
   *
   * @return \Drupal\helfi_react_search\DTO\LinkedEventsItem[]
   *   Enabled keyword.
   */
  public function getFilterKeywords() : array {
    return $this->deserializeAutocompleteField($this->get('field_event_list_keywords_filter'));
  }

  /**
   * Deserialize JSON field that uses linked events autocomplete widget.
   *
   * @return \Drupal\helfi_react_search\DTO\LinkedEventsItem[]
   *   Selected values.
   */
  private function deserializeAutocompleteField(FieldItemListInterface $field): array {
    /** @var \Symfony\Component\Serializer\SerializerInterface $serializer */
    $serializer = \Drupal::service('serializer');

    $keywords = [];
    foreach ($field as $value) {
      try {
        $keywords[] = $serializer->deserialize($value->getString(), LinkedEventsItem::class, 'json');
      }
      catch (ExceptionInterface) {
        // Ignore failing rows.
      }
    }

    return $keywords;
  }

  /**
   * Gets enabled filters.
   */
  public function getFilterSettings(): array {
    $filters = [];

    foreach (Filters::cases() as $filter) {
      if (!$this->get($filter->value)->isEmpty()) {
        $filters[$filter->value] = (boolean) $this->get($filter->value)->value;
      }
    }

    return $filters;
  }

}
