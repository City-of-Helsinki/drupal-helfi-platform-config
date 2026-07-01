<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel\SchemaOrg;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_platform_config\SchemaOrg\SchemaManager;
use Drupal\Tests\helfi_tpr_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the TPR schema.org builders and WebPage main entity linking.
 */
#[RunTestsInSeparateProcesses]
#[Group('helfi_tpr_config')]
class TprSchemaOrgBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('tpr_unit');
    $this->installEntitySchema('tpr_service');
  }

  /**
   * Builds the graph for a tpr_service and asserts the GovernmentService node.
   */
  public function testServiceGraph(): void {
    $service = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('tpr_service')
      ->create([
        'id' => 1,
        'name' => 'Early childhood education application',
        'description' => [
          'value' => '<p>How to apply for early childhood education.</p>',
          'summary' => 'How to apply.',
          'format' => 'plain_text',
        ],
      ]);
    $service->save();

    $graph = $this->buildGraph($service);
    $types = array_column($graph, '@type');

    // The base graph is still present.
    $this->assertContains('WebPage', $types);
    $this->assertContains('GovernmentService', $types);

    $node = $this->graphItem($graph, 'GovernmentService');
    $this->assertStringEndsWith('#service', $node['@id']);
    $this->assertSame('Early childhood education application', $node['name']);
    $this->assertSame('How to apply.', $node['description']);
    $this->assertSame('https://www.hel.fi/#organization', $node['provider']['@id']);
    $this->assertSame('Helsinki', $node['areaServed']['name']);

    // The service references the page it is the main entity of.
    $webpage = $this->graphItem($graph, 'WebPage');
    $this->assertSame($webpage['@id'], $node['mainEntityOfPage']['@id']);
  }

  /**
   * Builds the graph for a tpr_unit and asserts the Place node.
   */
  public function testUnitGraph(): void {
    $unit = $this->container->get('entity_type.manager')
      ->getStorage('tpr_unit')
      ->create([
        'id' => 1,
        'name' => 'Viiskulma Health Station',
        'www' => 'https://example.com/viiskulma',
        'phone' => ['+358 9 310 45930'],
        'email' => 'viiskulma@example.com',
        'address' => [
          'country_code' => 'FI',
          'locality' => 'Helsinki',
          'postal_code' => '00150',
          'address_line1' => 'Pursimiehenkatu 4',
        ],
        'latitude' => '60.158',
        'longitude' => '24.940',
        'provided_languages' => ['fi', 'sv'],
      ]);
    $unit->save();

    $graph = $this->buildGraph($unit);

    $node = $this->graphItem($graph, 'Place');
    $this->assertStringEndsWith('#place', $node['@id']);
    $this->assertSame('Viiskulma Health Station', $node['name']);
    $this->assertSame('+358 9 310 45930', $node['telephone']);
    $this->assertSame('viiskulma@example.com', $node['email']);

    // Address is mapped to a PostalAddress.
    $this->assertSame('PostalAddress', $node['address']['@type']);
    $this->assertSame('Pursimiehenkatu 4', $node['address']['streetAddress']);
    $this->assertSame('00150', $node['address']['postalCode']);
    $this->assertSame('Helsinki', $node['address']['addressLocality']);
    $this->assertSame('FI', $node['address']['addressCountry']);

    // Coordinates are mapped to GeoCoordinates as floats.
    $this->assertSame('GeoCoordinates', $node['geo']['@type']);
    $this->assertSame(60.158, $node['geo']['latitude']);
    $this->assertSame(24.940, $node['geo']['longitude']);

    // The place references the page it is the main entity of.
    $webpage = $this->graphItem($graph, 'WebPage');
    $this->assertSame($webpage['@id'], $node['mainEntityOfPage']['@id']);
  }

  /**
   * Builds the full schema.org graph for an entity.
   *
   * @phpstan-return array<int, array<string, mixed>>
   */
  private function buildGraph(EntityInterface $entity): array {
    $cacheability = new CacheableMetadata();
    $document = $this->container->get(SchemaManager::class)->build($entity, $cacheability);

    // Builders refined the shared cacheability with the entity cache tags.
    $this->assertNotEmpty(array_intersect($entity->getCacheTags(), $cacheability->getCacheTags()));

    return $document['@graph'];
  }

  /**
   * Returns the first graph entity of the given type.
   *
   * @param array<int, array<string, mixed>> $graph
   *   The schema.org graph.
   * @param string|array<int, string> $type
   *   The schema.org @type to find.
   *
   * @return array<string, mixed>
   *   The matching entity.
   */
  private function graphItem(array $graph, string|array $type): array {
    foreach ($graph as $item) {
      if (($item['@type'] ?? NULL) === $type) {
        return $item;
      }
    }
    $this->fail('No matching entity in graph.');
  }

}
