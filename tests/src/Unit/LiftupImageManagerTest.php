<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\helfi_platform_config\Image\ImageBuilderInterface;
use Drupal\helfi_platform_config\Image\LiftupImageManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests the liftup image builder collector.
 */
#[Group('helfi_platform_config')]
class LiftupImageManagerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The file URL generator prophecy.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\File\FileUrlGeneratorInterface>
   */
  private ObjectProphecy $fileUrlGenerator;

  /**
   * The liftup image manager under test.
   */
  private LiftupImageManager $sut;

  /**
   * The entity passed to the manager.
   */
  private EntityInterface $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fileUrlGenerator = $this->prophesize(FileUrlGeneratorInterface::class);
    $this->sut = new LiftupImageManager($this->fileUrlGenerator->reveal());
    $this->entity = $this->prophesize(EntityInterface::class)->reveal();
  }

  /**
   * Tests that a non-applicable builder produces no URL.
   */
  public function testBuildUrlReturnsNullWhenBuilderDoesNotApply() : void {
    $this->sut->add($this->createImageBuilderMock('public://image.jpg', FALSE)->reveal());

    $this->assertNull($this->sut->buildUrl($this->entity));
    $this->fileUrlGenerator->generateAbsoluteString(Argument::any())->shouldNotHaveBeenCalled();
  }

  /**
   * Tests that a builder returning NULL produces no URL.
   */
  public function testBuildUrlReturnsNullWhenBuilderReturnsNull() : void {
    $this->sut->add($this->createImageBuilderMock(NULL)->reveal());

    $this->assertNull($this->sut->buildUrl($this->entity));
    $this->fileUrlGenerator->generateAbsoluteString(Argument::any())->shouldNotHaveBeenCalled();
  }

  /**
   * Tests that a stream URI is converted to an absolute URL.
   */
  public function testBuildUrlReturnsAbsoluteUrlForStreamUri() : void {
    $this->fileUrlGenerator->generateAbsoluteString('public://image.jpg')
      ->willReturn('https://example.com/sites/default/files/image.jpg')
      ->shouldBeCalledOnce();
    $this->sut->add($this->createImageBuilderMock('public://image.jpg')->reveal());

    $this->assertSame('https://example.com/sites/default/files/image.jpg', $this->sut->buildUrl($this->entity));
  }

  /**
   * Tests that an external URI is returned unchanged.
   */
  public function testBuildUrlReturnsExternalUriUnchanged() : void {
    $this->sut->add($this->createImageBuilderMock('https://cdn.example.com/image.jpg')->reveal());

    $this->assertSame('https://cdn.example.com/image.jpg', $this->sut->buildUrl($this->entity));
    $this->fileUrlGenerator->generateAbsoluteString(Argument::any())->shouldNotHaveBeenCalled();
  }

  /**
   * Tests that the highest-priority builder URI is the one converted.
   */
  public function testBuildUrlUsesTheWinningBuilderUri() : void {
    $this->fileUrlGenerator->generateAbsoluteString('public://later.jpg')
      ->willReturn('https://example.com/sites/default/files/later.jpg')
      ->shouldBeCalledOnce();
    $this->sut->add($this->createImageBuilderMock('public://early.jpg')->reveal(), -10);
    $this->sut->add($this->createImageBuilderMock('public://later.jpg')->reveal());

    $this->assertSame('https://example.com/sites/default/files/later.jpg', $this->sut->buildUrl($this->entity));
  }

  /**
   * Creates a mock image builder.
   *
   * @param string|null $uri
   *   Return value for buildUri().
   * @param bool $applies
   *   Return value for applies().
   *
   * @return \Prophecy\Prophecy\ObjectProphecy<\Drupal\helfi_platform_config\Image\ImageBuilderInterface>
   *   Builder mock.
   */
  private function createImageBuilderMock(?string $uri, bool $applies = TRUE) : ObjectProphecy {
    $builder = $this->prophesize(ImageBuilderInterface::class);
    $builder->applies(Argument::any())->willReturn($applies);
    $builder->buildUri(Argument::any())->willReturn($uri);
    return $builder;
  }

}
