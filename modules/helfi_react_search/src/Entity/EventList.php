<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Entity;

use Drupal\helfi_react_search\DTO\LinkedEventsItem;
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
   * Get list of enabled filter keywords.
   *
   * @return \Drupal\helfi_react_search\DTO\LinkedEventsItem[]
   *   Enabled keyword.
   */
  public function getFilterKeywords() : array {
    /** @var \Symfony\Component\Serializer\SerializerInterface $serializer */
    $serializer = \Drupal::service('serializer');

    $keywords = [];
    foreach ($this->get('field_event_list_keywords_filter') as $value) {
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
