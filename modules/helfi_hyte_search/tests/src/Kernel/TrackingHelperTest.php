<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_hyte_search\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\helfi_hyte_search\TrackingHelper;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\helfi_tpr\Entity\Channel;
use Drupal\helfi_tpr\Entity\ErrandService;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Kernel tests for TrackingHelper.
 *
 * @group helfi_hyte_search
 * @coversDefaultClass \Drupal\helfi_hyte_search\TrackingHelper
 */
class TrackingHelperTest extends EntityKernelTestBase implements ServiceModifierInterface {

  use ProphecyTrait;

  /**
   * Additional modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    'helfi_hyte_search',
    'helfi_tpr',
    'language',
    'config_rewrite',
    'menu_link_content',
    'link',
    'media',
    'address',
    'telephone',
  ];

  /**
   * The mocked search index.
   */
  protected ObjectProphecy $index;

  /**
   * The mocked datasource.
   */
  protected ObjectProphecy $datasource;

  /**
   * The tracking helper.
   */
  protected TrackingHelper $trackingHelper;

  /**
   * Test Service Entity.
   */
  protected EntityInterface $service;

  /**
   * Test Channel Entity.
   */
  protected EntityInterface $channel;

  /**
   * Test Errand Service Entity.
   */
  protected EntityInterface $errandService;

  /**
   * Test Unit Entity.
   */
  protected EntityInterface $unit;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $service_definition = $container->getDefinition('entity_type.manager');
    $service_definition->setClass(TestEntityTypeManager::class);
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $entities = [
      'tpr_service',
      'tpr_service_channel',
      'tpr_errand_service',
      'tpr_unit',
    ];
    foreach ($entities as $entity) {
      $this->installEntitySchema($entity);
    }

    $this->installConfig(['system']);

    $this->datasource = $this->prophesize(DatasourceInterface::class);
    $this->datasource->canContainEntityReferences()->willReturn(TRUE);

    $this->index = $this->prophesize(IndexInterface::class);
    $this->index->getDatasources()->willReturn([
      'entity:tpr_service' => $this->datasource,
    ]);

    $entityHandler = $this->prophesize(EntityHandlerInterface::class);
    $entityHandler->willImplement(EntityStorageInterface::class);
    $entityHandler->load('hyte')->willReturn($this->index->reveal());
    assert($this->entityTypeManager instanceof TestEntityTypeManager);
    $this->entityTypeManager->setHandler('search_api_index', 'storage', $entityHandler->reveal());

    $this->trackingHelper = new TrackingHelper($this->entityTypeManager);

    $this->channel = Channel::create([
      'name' => 'Test channel',
      'id' => $this->generateRandomEntityId(),
      'status' => TRUE,
    ]);
    $this->channel->save();

    $this->errandService = ErrandService::create([
      'name' => 'Test errand service',
      'id' => $this->generateRandomEntityId(),
      'status' => TRUE,
      'channels' => [$this->channel->id()],
    ]);
    $this->errandService->save();

    $this->service = Service::create([
      'name' => 'Test service',
      'id' => $this->generateRandomEntityId(),
      'status' => TRUE,
      'errand_services' => [$this->errandService->id()],
    ]);
    $this->service->save();

    $this->unit = Unit::create([
      'name' => 'Test unit',
      'id' => $this->generateRandomEntityId(),
      'status' => TRUE,
      'services' => [$this->service->id()],
    ]);
    $this->unit->save();
  }

  /**
   * Test updating a channel.
   */
  public function testUpdateChannel() {
    $this->index->trackItemsUpdated('entity:tpr_service', [$this->service->id() . ':en'])->shouldBeCalled();
    $this->trackingHelper->trackTprUpdate($this->channel);
  }

  /**
   * Test inserting a new channel.
   */
  public function testInsertChannel() {
    $this->index->trackItemsUpdated('entity:tpr_service', [$this->service->id() . ':en'])->shouldNotBeCalled();
    $this->trackingHelper->trackTprUpdate($this->channel, TRUE);
  }

  /**
   * Test updating an errand service.
   */
  public function testUpdateErrandService() {
    $this->index->trackItemsUpdated('entity:tpr_service', [$this->service->id() . ':en'])->shouldBeCalled();
    $this->trackingHelper->trackTprUpdate($this->errandService);
  }

  /**
   * Test updating a unit.
   */
  public function testUpdateUnit() {
    $this->index->trackItemsUpdated('entity:tpr_service', [$this->service->id() . ':en'])->shouldBeCalled();
    $this->trackingHelper->trackTprUpdate($this->unit);
  }

}

/**
 * Test entity type manager.
 */
class TestEntityTypeManager extends EntityTypeManager {

  /**
   * Helper method to set a handler for an entity type for testing.
   */
  public function setHandler(string $entity_type_id, string $handler_type, EntityHandlerInterface $handler) {
    $this->handlers[$handler_type][$entity_type_id] = $handler;
  }

}
