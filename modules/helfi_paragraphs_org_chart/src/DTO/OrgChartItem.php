<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_org_chart\DTO;

use Drupal\helfi_api_base\Ahjo\OrganizationType;

/**
 * DTO for päätökset org chart API responses.
 */
final readonly class OrgChartItem {

  /**
   * The constructor.
   *
   * @phpstan-param self[] $children
   */
  public function __construct(
    public string $id,
    public string $title,
    public OrganizationType $type,
    public ?string $url,
    public array $children,
  ) {
  }

  /**
   * Parse OrgChartItems from /ahjo_api/org-chart/:id/:depth response.
   */
  public static function fromResponse(\stdClass $response): self {
    $type = $response->type ?? NULL;

    return new self(
      (string) $response->id,
      (string) $response->title,
      (is_numeric($type) ? OrganizationType::tryFrom((int) $type) : NULL) ?? OrganizationType::UNKNOWN,
      $response->url ?? NULL,
      array_map(self::fromResponse(...), $response->children ?? [])
    );
  }

}
