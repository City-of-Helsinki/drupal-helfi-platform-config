<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\EntityReferenceSelection;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\helfi_platform_config\Plugin\EntityReferenceSelection\MultisiteContentSelection;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\helfi_platform_config\Traits\InstallConfigEntityTrait;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests multisite content autocomplete labels.
 */
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
    'serialization',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->installConfigEntity('helfi_platform_config', 'external_entities.external_entity_type.helfi_multisite_content');
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

    $selection = $this->container
      ->get('plugin.manager.entity_reference_selection')
      ->getInstance([
        'target_type' => MultisiteContentId::ENTITY_TYPE_ID,
        'handler' => 'default:helfi_multisite_content',
      ]);
    $this->assertInstanceOf(MultisiteContentSelection::class, $selection);

    return $selection->getReferenceableEntities('Lib');
  }

}
