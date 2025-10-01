<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\TextConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_platform_config\TextConverter\TextConverterInterface;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests text converter.
 *
 * @group helfi_recommendations
 */
class TextConverterManagerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests converter priority.
   */
  public function testConverterPriority() : void {
    $converter1 = $this->prophesize(TextConverterInterface::class);
    $converter2 = $this->prophesize(TextConverterInterface::class);

    $converter1->applies(Argument::any())
      ->shouldBeCalled()
      ->willReturn(TRUE);

    $converter1->convert(Argument::any())
      ->shouldBeCalled()
      ->willReturn("Hello, world");

    $converter2->applies(Argument::any())
      ->shouldNotBeCalled();

    $converter2->convert(Argument::any())
      ->shouldNotBeCalled();

    $sut = new TextConverterManager();
    $sut->add($converter1->reveal(), 10);
    $sut->add($converter1->reveal(), -10);

    $text = $sut->convert($this->prophesize(EntityInterface::class)->reveal());

    $this->assertEquals("Hello, world", $text);
  }

  /**
   * Tests converter fallback.
   */
  public function testConverterFallback() : void {
    $converter = $this->prophesize(TextConverterInterface::class);

    $converter->applies(Argument::any())
      ->shouldBeCalled()
      ->willReturn(FALSE);

    $converter->convert(Argument::any())
      ->shouldNotBeCalled();

    $sut = new TextConverterManager();
    $sut->add($converter->reveal());

    $text = $sut->convert($this->prophesize(EntityInterface::class)->reveal());

    $this->assertNull($text);
  }

}
