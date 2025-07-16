<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_tpr_config\Entity\Service;
use Drupal\KernelTests\KernelTestBase;
use Drupal\helfi_tpr_config\Entity\Unit;
use Drupal\Core\Url;

/**
 * Tests helfi_tpr_config Entity class overrides.
 *
 * @covers \Drupal\helfi_tpr_config\Entity\Unit::getWebsiteUrl
 * @group helfi_tpr_config
 */
class EntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'link',
    'user',
    'file',
    'media',
    'image',
    'address',
    'menu_link_content',
    'telephone',
    'metatag',
    'metatag_open_graph',
    'metatag_twitter_cards',
    'entity_reference_revisions',
    'paragraphs',
    'paragraphs_library',
    'options',
    'token',
    'helfi_api_base',
    'helfi_recommendations',
    'helfi_tpr',
    'helfi_tpr_config',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Set up the test environment.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('tpr_unit');
    $this->installEntitySchema('tpr_service');
    $this->entityTypeManager = $this->container->get(EntityTypeManagerInterface::class);
  }

  /**
   * Tests getWebsiteUrl() with newly created entity.
   */
  public function testGetWebsiteUrl(): void {
    $storage = $this->entityTypeManager->getStorage('tpr_unit');
    $unit = $storage->create([
      'id' => 'test-unit',
      'type' => 'tpr_unit',
      'www' => 'https://example.com',
    ]);
    $unit->save();
    $this->assertInstanceOf(Unit::class, $unit);

    $url = $unit->getWebsiteUrl();
    $this->assertInstanceOf(Url::class, $url);
    $this->assertEquals('https://example.com', $url->getUri());
  }

  /**
   * Tests that Service entity class is overridden.
   */
  public function testServiceClass() : void {
    $storage = $this->entityTypeManager->getStorage('tpr_service');
    $service = $storage->create([
      'id' => 'test-unit',
      'type' => 'tpr_unit',
    ]);
    $service->save();
    $this->assertInstanceOf(Service::class, $service);
  }

}
