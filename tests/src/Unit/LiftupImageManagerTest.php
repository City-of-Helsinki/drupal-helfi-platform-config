<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\Entity\EntityInterface;
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

    $this->sut = new LiftupImageManager();
    $this->entity = $this->prophesize(EntityInterface::class)->reveal();
  }

  /**
   * Tests that a non-applicable builder produces no URI.
   */
  public function testBuildUrlReturnsNullWhenBuilderDoesNotApply() : void {
    $this->sut->add($this->createImageBuilderMock('public://image.jpg', FALSE)->reveal());

    $this->assertNull($this->sut->buildUrl($this->entity));
  }

  /**
   * Tests that a builder returning NULL produces no URI.
   */
  public function testBuildUrlReturnsNullWhenBuilderReturnsNull() : void {
    $this->sut->add($this->createImageBuilderMock(NULL)->reveal());

    $this->assertNull($this->sut->buildUrl($this->entity));
  }

  /**
   * Tests that the builder URI is returned unchanged.
   */
  public function testBuildUrlReturnsBuilderUriUnchanged() : void {
    $this->sut->add($this->createImageBuilderMock('public://image.jpg')->reveal());

    $this->assertSame('public://image.jpg', $this->sut->buildUrl($this->entity));
  }

  /**
   * Tests that the highest-priority builder URI wins.
   */
  public function testBuildUrlUsesTheWinningBuilderUri() : void {
    $this->sut->add($this->createImageBuilderMock('public://early.jpg')->reveal(), -10);
    $this->sut->add($this->createImageBuilderMock('public://later.jpg')->reveal());

    $this->assertSame('public://later.jpg', $this->sut->buildUrl($this->entity));
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
