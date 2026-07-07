<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_tpr\Entity\Service as ServiceBase;
use Drupal\helfi_tpr\Entity\Unit as UnitBase;
use Drupal\helfi_tpr_config\Entity\Service;
use Drupal\helfi_tpr_config\Entity\Unit;
use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests helfi_tpr_config Entity class overrides.
 *
 * @covers \Drupal\helfi_tpr_config\Entity\Unit::getWebsiteUrl
 */
#[Group('helfi_tpr_config')]
#[RunTestsInSeparateProcesses]
class EntityTest extends KernelTestBase {

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
    // Make sure Unit extends the original unit class.
    $this->assertInstanceOf(UnitBase::class, $unit);

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
    // Make sure Unit extends the original service class.
    $this->assertInstanceOf(ServiceBase::class, $service);
  }

}
