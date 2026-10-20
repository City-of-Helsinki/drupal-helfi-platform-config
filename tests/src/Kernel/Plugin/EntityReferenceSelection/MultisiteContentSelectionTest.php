<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\EntityReferenceSelection;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\helfi_api_base\Environment\ActiveProjectRoles;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ProjectMetadata;
use Drupal\helfi_api_base\Environment\ProjectRoleEnum;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\helfi_platform_config\Plugin\EntityReferenceSelection\MultisiteContentSelection;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\helfi_platform_config\Traits\InstallConfigEntityTrait;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests multisite content autocomplete labels.
 */
#[CoversClass(MultisiteContentSelection::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class MultisiteContentSelectionTest extends KernelTestBase {

  use ApiTestTrait;
  use ElasticTrait;
  use EnvironmentResolverTrait;
  use InstallConfigEntityTrait;

  /**
   * An entity id that can be parsed back out of an autocomplete value.
   */
  private const string ENTITY_ID = 'site_etusivu/entity:node/1:fi';

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
  protected function setUp() : void {
    parent::setUp();

    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installConfig(['system', 'external_entities']);
    $this->installEntitySchema('user');
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->setEtusivuIndexRole(TRUE);

    $history = [];
    $this->container->set(
      'helfi_platform_config.etusivu_elastic_client',
      ClientBuilder::create()
        ->setHttpClient($this->createMockHistoryMiddlewareHttpClient($history))
        ->build(),
    );

    $this->installConfigEntity('helfi_platform_config', 'external_entities.external_entity_type.helfi_multisite_content');
    $this->installEntitySchema(MultisiteContentId::ENTITY_TYPE_ID);
    $this->container->get('entity_type.manager')->clearCachedDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * The suggestion label includes the external URL.
   */
  public function testLabelIncludesExternalUrl() : void {
    $options = $this->referenceableEntities([
      'label' => ['Library'],
      'url' => ['https://www.hel.fi/en/library'],
    ]);

    $this->assertSame(
      'Library (https://www.hel.fi/en/library)',
      $options[MultisiteContentId::ENTITY_TYPE_ID][self::ENTITY_ID],
    );
  }

  /**
   * A result without an external URL keeps the title label.
   */
  public function testLabelUnchangedWhenUrlIsMissing() : void {
    $options = $this->referenceableEntities([
      'label' => ['Library'],
    ]);

    $this->assertSame(
      'Library',
      $options[MultisiteContentId::ENTITY_TYPE_ID][self::ENTITY_ID],
    );
  }

  /**
   * Runs an autocomplete match against one stubbed search hit.
   *
   * @param array<string, list<string>> $source
   *   Elasticsearch _source for the hit.
   *
   * @return array<string, array<int|string, string>>
   *   Referenceable entity labels, keyed by bundle and id.
   */
  private function referenceableEntities(array $source) : array {
    $hit = [
      'hits' => [
        'hits' => [
          [
            '_id' => self::ENTITY_ID,
            '_source' => $source,
          ],
        ],
      ],
    ];
    $history = [];
    $this->container->set(
      'helfi_platform_config.etusivu_elastic_client',
      ClientBuilder::create()
        ->setHttpClient($this->createMockHistoryMiddlewareHttpClient($history, [
          $this->createElasticsearchResponse($hit),
          $this->createElasticsearchResponse($hit),
        ]))
        ->build(),
    );
    $this->container->get('entity_type.manager')->clearCachedDefinitions();

    $selection = $this->container
      ->get('plugin.manager.entity_reference_selection')
      ->getInstance([
        'target_type' => MultisiteContentId::ENTITY_TYPE_ID,
        'handler' => 'default:helfi_multisite_content',
      ]);
    $this->assertInstanceOf(MultisiteContentSelection::class, $selection);

    return $selection->getReferenceableEntities('Lib');
  }

  /**
   * Stubs whether the active project has the etusivu index role.
   */
  private function setEtusivuIndexRole(bool $enabled) : void {
    $project = new Project(
      'example',
      new ProjectMetadata('https://example.com/example'),
      roles: $enabled ? [ProjectRoleEnum::HasEtusivuIndex] : [],
    );
    $resolver = $this->createMock(EnvironmentResolverInterface::class);
    $resolver->method('getActiveProject')->willReturn($project);
    $this->container->set(ActiveProjectRoles::class, new ActiveProjectRoles($resolver));
  }

}
