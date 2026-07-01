<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\SchemaOrg;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\helfi_platform_config\SchemaOrg\SchemaManager;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Runs all platform schema.org builders together.
 */
#[RunTestsInSeparateProcesses]
#[Group('helfi_platform_config')]
final class SchemaOrgBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Builds the graph for a content entity and asserts the base entities.
   */
  public function testBuildsBaseGraph(): void {
    $entity = EntityTest::create(['name' => 'Test page']);
    $entity->save();

    $cacheability = new CacheableMetadata();
    $graph = $this->container->get(SchemaManager::class)->build($entity, $cacheability);

    // The document is wrapped correctly and is non-empty.
    $this->assertSame('https://schema.org', $graph['@context']);
    $this->assertNotEmpty($graph['@graph']);

    // SiteIdentityBuilder and WebPageBuilder contributed the base entities.
    $types = array_column($graph['@graph'], '@type');
    $this->assertContains('GovernmentOrganization', $types);
    $this->assertContains('WebSite', $types);
    $this->assertContains('WebPage', $types);

    // The WebPage describes the entity.
    $webpage = $this->graphItem($graph['@graph'], 'WebPage');
    $this->assertSame('Test page', $webpage['name']);
    $this->assertStringEndsWith('#webpage', $webpage['@id']);
    $this->assertNotEmpty($webpage['inLanguage']);

    // Builders refined the shared cacheability through the manager.
    $this->assertContains('config:helfi_platform_config.schema_settings', $cacheability->getCacheTags());
    $this->assertNotEmpty(array_intersect($entity->getCacheTags(), $cacheability->getCacheTags()));
  }

  /**
   * Returns the first graph entity of the given @type.
   *
   * @param array<int, array<string, mixed>> $graph
   *   The schema.org graph.
   * @param string $type
   *   The schema.org @type to find.
   *
   * @return array<string, mixed>
   *   The matching entity.
   */
  private function graphItem(array $graph, string $type): array {
    foreach ($graph as $item) {
      if (($item['@type'] ?? NULL) === $type) {
        return $item;
      }
    }
    $this->fail("No '$type' entity in graph.");
  }

}
