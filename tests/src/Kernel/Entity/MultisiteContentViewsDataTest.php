<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Entity;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\ElasticTrait;
use Drupal\Tests\helfi_platform_config\Traits\InstallConfigEntityTrait;
use Drupal\views\ViewsData;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Views data for references to multisite content.
 *
 * This tests that the reverse relationship of an entity reference to multisite
 * content is correctly described in Views data. This is needed as long as
 * multisite content is a target of an entity reference field, even if there is
 * no view display that would actually use it.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class MultisiteContentViewsDataTest extends KernelTestBase {

  use ApiTestTrait;
  use ElasticTrait;
  use InstallConfigEntityTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity',
    'entity_reference_revisions',
    'external_entities',
    'field',
    'file',
    'language',
    'node',
    'paragraphs',
    'serialization',
    'system',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['field', 'paragraphs']);

    $history = [];
    $this->container->set(
      'helfi_platform_config.etusivu_elastic_client',
      ClientBuilder::create()
        ->setHttpClient($this->createMockHistoryMiddlewareHttpClient($history))
        ->build(),
    );
    $this->installConfigEntity('helfi_platform_config', 'external_entities.external_entity_type.helfi_multisite_content');

    ParagraphsType::create([
      'id' => 'content_cards',
      'label' => 'Content cards',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_content_cards_content_ext',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => MultisiteContentId::ENTITY_TYPE_ID,
      ],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_content_cards_content_ext',
      'entity_type' => 'paragraph',
      'bundle' => 'content_cards',
      'label' => 'Content',
    ])->save();
  }

  /**
   * A reference to multisite content can be described without a null table key.
   */
  public function testReverseRelationshipUsesEntityTypeId() : void {
    $views_data = $this->container->get('views.views_data');
    $this->assertInstanceOf(ViewsData::class, $views_data);
    $views_data->clear();
    $data = $views_data->getAll();

    $this->assertArrayHasKey(
      'reverse__paragraph__field_content_cards_content_ext',
      $data[MultisiteContentId::ENTITY_TYPE_ID],
    );
  }

}
