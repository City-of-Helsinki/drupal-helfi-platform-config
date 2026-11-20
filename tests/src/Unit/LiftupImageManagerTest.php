<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_platform_config\Image\ImageBuilderInterface;
use Drupal\helfi_platform_config\Image\LiftupImageManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

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
    $this->sut->add($this->createImageBuilder('public://image.jpg', FALSE));

    $this->assertNull($this->sut->buildUrl($this->entity));
  }

  /**
   * Tests that a builder returning NULL produces no URI.
   */
  public function testBuildUrlReturnsNullWhenBuilderReturnsNull() : void {
    $this->sut->add($this->createImageBuilder(NULL));

    $this->assertNull($this->sut->buildUrl($this->entity));
  }

  /**
   * Tests that the highest-priority builder URI wins.
   */
  public function testBuildUrlUsesTheWinningBuilderUri() : void {
    $this->sut->add($this->createImageBuilder('public://early.jpg'), -10);
    $this->sut->add($this->createImageBuilder('public://later.jpg'));

    $this->assertSame('public://later.jpg', $this->sut->buildUrl($this->entity));
  }

  /**
   * Creates an image builder.
   *
   * @return \Drupal\helfi_platform_config\Image\ImageBuilderInterface
   *   The image builder.
   */
  private function createImageBuilder(?string $uri, bool $applies = TRUE) : ImageBuilderInterface {
    return new readonly class($uri, $applies) implements ImageBuilderInterface {

      public function __construct(
        private ?string $uri,
        private bool $applies,
      ) {
      }

      /**
       * {@inheritdoc}
       */
      public function applies(?EntityInterface $entity) : bool {
        return $this->applies;
      }

      /**
       * {@inheritdoc}
       */
      public function buildUri(?EntityInterface $entity) : ?string {
        return $this->uri;
      }

    };
  }

}
