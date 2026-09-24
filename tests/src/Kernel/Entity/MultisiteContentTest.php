<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Entity;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\external_entities\Entity\ExternalEntityType;
use Drupal\helfi_platform_config\Entity\ExternalEntity\MultisiteContent;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the MultisiteContent external entity bundle class.
 */
#[CoversClass(MultisiteContent::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class MultisiteContentTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity',
    'external_entities',
    'field',
    'language',
    'serialization',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Triggers rebuilding routes.
    // https://www.drupal.org/project/external_entities/issues/3549828.
    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installConfig(['system', 'external_entities']);
    $this->installEntitySchema('user');

    $history = [];
    $client = ClientBuilder::create()
      ->setHttpClient($this->createMockHistoryMiddlewareHttpClient($history))
      ->build();
    $this->container->set('helfi_platform_config.etusivu_elastic_client', $client);

    $module_path = $this->container
      ->get('extension.list.module')
      ->getPath('helfi_platform_config');
    $values = Yaml::decode((string) file_get_contents(
      $module_path . '/config/install/external_entities.external_entity_type.helfi_multisite_content.yml',
    ));
    unset($values['uuid']);
    ExternalEntityType::create($values)->save();

    $this->installEntitySchema(MultisiteContentId::ENTITY_TYPE_ID);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * Tests canonical URL route parameters use the split path segments.
   */
  public function testCanonicalUrlRouteParameters(): void {
    $entity = $this->createMultisiteContent([
      'id' => 'site_etusivu/entity:node/8420:en',
    ]);

    $url = $entity->toUrl('canonical');
    $this->assertSame('entity.helfi_multisite_content.canonical', $url->getRouteName());

    $parameters = $url->getRouteParameters();
    $this->assertSame('site_etusivu', $parameters['instance']);
    $this->assertSame('entity-node', $parameters['datasource']);
    $this->assertSame('8420-en', $parameters['item']);
    $this->assertArrayNotHasKey(MultisiteContentId::ENTITY_TYPE_ID, $parameters);
  }

  /**
   * Tests that an unparseable id does not add split route parameters.
   */
  public function testCanonicalUrlRouteParametersWithInvalidId(): void {
    $entity = $this->createMultisiteContent([
      'id' => 'not-a-valid-id',
    ]);

    $parameters = $entity->toUrl('canonical')->getRouteParameters();
    $this->assertArrayNotHasKey('instance', $parameters);
    $this->assertArrayNotHasKey('datasource', $parameters);
    $this->assertArrayNotHasKey('item', $parameters);
    $this->assertArrayNotHasKey(MultisiteContentId::ENTITY_TYPE_ID, $parameters);
  }

  /**
   * Tests resolving the external URL from entity fields.
   */
  #[DataProvider('providerExternalUrls')]
  public function testGetExternalUrl(?string $entity_url, ?string $expected): void {
    $values = [];
    if ($entity_url !== NULL) {
      $values['entity_url'] = $entity_url;
    }

    $url = $this->createMultisiteContent($values)->getExternalUrl();
    if ($expected === NULL) {
      $this->assertNull($url);
      return;
    }

    $this->assertNotNull($url);
    $this->assertTrue($url->getOption('absolute'));
    $this->assertSame($expected, $url->toString());
  }

  /**
   * External URL field values and the expected generated URL.
   *
   * @return array<string, array{0: string|null, 1: string|null}>
   *   Test cases.
   */
  public static function providerExternalUrls(): array {
    return [
      'missing url' => [NULL, NULL],
      'empty url' => ['', NULL],
      'absolute uri' => [
        'https://www.hel.fi/fi/news/example',
        'https://www.hel.fi/fi/news/example',
      ],
    ];
  }

  /**
   * Tests that a site-relative path is turned into an absolute URL.
   */
  public function testGetExternalUrlFromInternalPath(): void {
    $url = $this->createMultisiteContent([
      'entity_url' => '/fi/news/example',
    ])->getExternalUrl();

    $this->assertNotNull($url);
    $this->assertTrue($url->getOption('absolute'));
    $this->assertFalse($url->isExternal());
    $this->assertStringEndsWith('/fi/news/example', $url->toString());
  }

  /**
   * Creates a multisite content entity.
   *
   * @param array<string, mixed> $values
   *   Entity values.
   */
  private function createMultisiteContent(array $values = []): MultisiteContent {
    $entity = $this->container
      ->get('entity_type.manager')
      ->getStorage(MultisiteContentId::ENTITY_TYPE_ID)
      ->create($values + [
        'id' => 'site_etusivu/entity:node/1:en',
        'title' => 'Test content',
      ]);
    $this->assertInstanceOf(MultisiteContent::class, $entity);

    return $entity;
  }

}
