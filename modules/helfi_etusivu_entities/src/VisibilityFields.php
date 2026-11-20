<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

/**
 * The fields that limit which pages an announcement or survey is shown on.
 */
final readonly class VisibilityFields {

  /**
   * Constructs a new instance.
   *
   * @param string|null $allPages
   *   The boolean field that shows the entity on all pages. NULL when the
   *   bundle has no such field, in which case an entity without any page
   *   rules is shown on all pages.
   * @param array<string, string> $pageReferences
   *   Reference fields to single pages, keyed by the target entity type.
   * @param array<string, string> $regionReferences
   *   Reference fields to units and services, keyed by the target entity
   *   type. Those cover a broader area than a single page.
   * @param string $paths
   *   The string field that stores page path patterns.
   */
  public function __construct(
    public ?string $allPages,
    public array $pageReferences,
    public array $regionReferences,
    public string $paths,
  ) {}

  /**
   * Gets the announcement fields.
   */
  public static function announcement(): self {
    return new self(
      allPages: 'field_announcement_all_pages',
      pageReferences: ['node' => 'field_announcement_content_pages'],
      regionReferences: [
        'tpr_unit' => 'field_announcement_unit_pages',
        'tpr_service' => 'field_announcement_service_pages',
      ],
      paths: 'field_announcement_page_paths',
    );
  }

  /**
   * Gets the survey fields.
   */
  public static function survey(): self {
    return new self(
      allPages: NULL,
      pageReferences: ['node' => 'field_survey_content_pages'],
      regionReferences: [],
      paths: 'field_survey_page_paths',
    );
  }

  /**
   * Gets all reference fields, keyed by the target entity type.
   *
   * @return array<string, string>
   *   The field names.
   */
  public function references(): array {
    return $this->pageReferences + $this->regionReferences;
  }

}
