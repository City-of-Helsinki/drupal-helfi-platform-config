<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_platform_config\SchemaOrg\SchemaManager;

/**
 * Injects the page-level schema.org JSON-LD graph into the document head.
 */
final readonly class SchemaOrgHooks {

  public function __construct(
    private SchemaManager $schemaManager,
    private EntityVersionMatcher $entityVersionMatcher,
  ) {
  }

  /**
   * Implements hook_page_attachments().
   *
   * @phpstan-param array<mixed> $attachments
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    ['entity' => $entity] = $this->entityVersionMatcher->getType();

    // Entity version matchers return FALSE if page has no entity.
    $entity = $entity ?: NULL;

    $cacheability = CacheableMetadata::createFromRenderArray($attachments);
    $graph = $this->schemaManager->build($entity, $cacheability);
    $cacheability->applyTo($attachments);

    if (!$graph) {
      return;
    }

    $attachments['#attached']['html_head'][] = [
      [
        '#tag' => 'script',
        '#attributes' => ['type' => 'application/ld+json'],
        '#value' => json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      ],
      'helfi_schema_org',
    ];
  }

}
