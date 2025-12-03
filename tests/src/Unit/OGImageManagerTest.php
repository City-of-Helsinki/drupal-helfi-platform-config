<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\helfi_platform_config\Token\OGImageBuilderInterface;
use Drupal\helfi_platform_config\Token\OGImageManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests og image builder collector.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Token\OGImageManager
 * @group helfi_platform_config
 */
class OGImageManagerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests builder.
   *
   * @covers ::buildUrl
   * @covers ::add
   * @covers ::getBuilders
   */
  public function testBuildUrl() : void {
    $sut = $this->getSut();
    $entity = $this->prophesize(EntityInterface::class)->reveal();

    // First builder does not apply.
    $sut->add($this->createImageBuilderMock('https://1', FALSE)->reveal());
    $this->assertEquals(NULL, $sut->buildUrl($entity));

    // Second builder applies but returns NULL.
    $sut->add($this->createImageBuilderMock(NULL)->reveal());
    $this->assertEquals(NULL, $sut->buildUrl($entity));

    // Third builder applies, priority is lower.
    $sut->add($this->createImageBuilderMock('https://3')->reveal(), -10);
    $this->assertEquals('https://3', $sut->buildUrl($entity));

    // Builder with the lowers priority gets overwritten by '3'.
    $builder4 = $this->createImageBuilderMock('https://4');
    $sut->add($builder4->reveal(), -100);
    $this->assertEquals('https://3', $sut->buildUrl($entity));
    $builder4->buildUri(Argument::any())->shouldHaveBeenCalled();
  }

  /**
   * Gets service under test.
   *
   * @param \Drupal\Core\File\FileUrlGeneratorInterface|null $fileUrlGenerator
   *   File url generator mock.
   *
   * @returns \Drupal\helfi_platform_config\Token\OGImageManager
   *   The open graph image manager.
   */
  private function getSut(?FileUrlGeneratorInterface $fileUrlGenerator = NULL) : OGImageManager {
    $moduleHandler = $this->prophesize(ModuleHandlerInterface::class);

    if (!$fileUrlGenerator) {
      $prophecy = $this->prophesize(FileUrlGeneratorInterface::class);
      $prophecy->generateAbsoluteString(Argument::any())->willReturnArgument(0);
      $fileUrlGenerator = $prophecy->reveal();
    }

    return new OGImageManager(
      $moduleHandler->reveal(),
      $fileUrlGenerator,
    );
  }

  /**
   * Creates mock image builder.
   *
   * @param string|null $url
   *   Return value for buildUrl().
   * @param bool $applies
   *   Return value for applies().
   *
   * @return \Drupal\helfi_platform_config\Token\OGImageBuilderInterface|\Prophecy\Prophecy\ObjectProphecy
   *   Builder mock.
   */
  private function createImageBuilderMock(?string $url, bool $applies = TRUE) : OGImageBuilderInterface|ObjectProphecy {
    $builder = $this->prophesize(OGImageBuilderInterface::class);
    $builder->applies(Argument::any())->willReturn($applies);
    $builder->buildUri(Argument::any())->willReturn($url);
    return $builder;
  }

}
