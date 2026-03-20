<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drush\Style\DrushStyle;
use Drupal\helfi_platform_config\ClearSiteData;
use Drupal\helfi_platform_config\Drush\Commands\ClearSiteDataCommands;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ClearSiteDataCommands.
 */
#[CoversClass(ClearSiteDataCommands::class)]
#[Group('helfi_platform_config')]
final class ClearSiteDataCommandsTest extends UnitTestCase {

  /**
   * The ClearSiteData mock.
   */
  private ClearSiteData&MockObject $clearSiteData;

  /**
   * The DrushStyle mock.
   */
  private DrushStyle&MockObject $io;

  /**
   * The system under test.
   */
  private ClearSiteDataCommands $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->clearSiteData = $this->createMock(ClearSiteData::class);
    $this->io = $this->createMock(DrushStyle::class);
    $this->sut = new ClearSiteDataCommands($this->clearSiteData);
    $this->injectIo();
  }

  /**
   * Tests disable invokes the service and reports success.
   */
  public function testDisable(): void {
    $this->io->expects($this->once())
      ->method('success')
      ->with('"Clear-Site-Data"-header disabled.');

    $this->clearSiteData
      ->expects($this->once())
      ->method('disable');

    $this->assertSame(DrushCommands::EXIT_SUCCESS, $this->sut->disable());
  }

  /**
   * Tests status output when the header is disabled.
   */
  public function testStatusWhenDisabled(): void {
    $this->io->expects($this->once())
      ->method('info')
      ->with('"Clear-Site-Data"-header is disabled.');

    $this->clearSiteData->method('isEnabled')->willReturn(FALSE);

    $this->assertSame(DrushCommands::EXIT_SUCCESS, $this->sut->status());
  }

  /**
   * Tests status output when the header is enabled.
   */
  public function testStatusWhenEnabled(): void {
    $expire = 1_700_000_000;
    $expectedDate = date('Y-m-d H:i:s', $expire);

    $this->io->expects($this->once())
      ->method('info')
      ->with(sprintf(
        '"Clear-Site-Data"-header is enabled with directives: %s and expiration time %s.',
        implode(',', ['cache', 'cookies']),
        $expectedDate,
      ));

    $this->clearSiteData->method('isEnabled')->willReturn(TRUE);
    $this->clearSiteData->method('getActiveDirectives')->willReturn(['cache', 'cookies']);
    $this->clearSiteData->method('getActiveExpireAfter')->willReturn($expire);

    $this->assertSame(DrushCommands::EXIT_SUCCESS, $this->sut->status());
  }

  /**
   * Tests enable collects input, enables the header, and prints success.
   */
  public function testEnable(): void {
    $selected = ['cache', 'storage'];
    $ttl = '2';
    $expireAfter = 1_700_000_100;

    $this->io->expects($this->once())
      ->method('choice')
      ->with(
        'Select directives',
        ClearSiteData::VALID_DIRECTIVES,
        NULL,
        TRUE,
        15,
        NULL,
        '',
        TRUE,
      )
      ->willReturn($selected);
    $this->io->expects($this->once())
      ->method('ask')
      ->with(
        'Give expiration time in hours',
        '1',
        NULL,
        '',
        TRUE,
        $this->isInstanceOf(\Closure::class),
        '',
      )
      ->willReturn($ttl);
    $expectedDate = date('Y-m-d H:i:s', $expireAfter);
    $this->io->expects($this->once())
      ->method('success')
      ->with(sprintf(
        '"Clear-Site-Data"-header enabled with directives: %s and expiration time: %s hours (%s).',
        implode(',', $selected),
        $ttl,
        $expectedDate,
      ));

    $this->clearSiteData
      ->expects($this->once())
      ->method('enable')
      ->with($selected, 2);

    $this->clearSiteData
      ->method('getActiveDirectives')
      ->willReturnOnConsecutiveCalls(NULL, $selected);
    $this->clearSiteData
      ->method('getActiveExpireAfter')
      ->willReturnOnConsecutiveCalls(NULL, $expireAfter);

    $this->assertSame(DrushCommands::EXIT_SUCCESS, $this->sut->enable());
  }

  /**
   * Tests ask validation rejects non-numeric and out-of-range values.
   */
  public function testEnableAskValidation(): void {
    $capturedValidate = NULL;
    $this->io->method('choice')->willReturn(['cache']);
    $this->io->expects($this->once())
      ->method('ask')
      ->willReturnCallback(function (
        $question,
        $default,
        $validator,
        $placeholder,
        $required,
        $validate,
      ) use (&$capturedValidate) {
        $capturedValidate = $validate;
        return '1';
      });
    $this->io->expects($this->once())->method('success');

    $this->clearSiteData->method('getActiveDirectives')->willReturnOnConsecutiveCalls(NULL, ['cache']);
    $this->clearSiteData->method('getActiveExpireAfter')->willReturnOnConsecutiveCalls(NULL, 1_700_000_000);
    $this->clearSiteData->expects($this->once())->method('enable')->with(['cache'], 1);

    $this->sut->enable();

    $this->assertInstanceOf(\Closure::class, $capturedValidate);
    $error = ($capturedValidate)('abc');
    $this->assertIsString($error);
    $error = ($capturedValidate)(ClearSiteData::MIN_EXPIRE_TIME - 1);
    $this->assertIsString($error);
    $error = ($capturedValidate)(ClearSiteData::MAX_EXPIRE_TIME + 1);
    $this->assertIsString($error);
    $this->assertNull(($capturedValidate)(random_int(ClearSiteData::MIN_EXPIRE_TIME, ClearSiteData::MAX_EXPIRE_TIME)));
  }

  /**
   * Assigns the DrushStyle mock to the command's IO property.
   */
  private function injectIo(): void {
    $property = new \ReflectionProperty(DrushCommands::class, 'io');
    $property->setValue($this->sut, $this->io);
  }

}
