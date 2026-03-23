<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Drush\Commands;

use Drupal\helfi_platform_config\ClearSiteData;
use Drupal\helfi_platform_config\Drush\Commands\ClearSiteDataCommand;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Unit tests for ClearSiteDataCommand.
 */
#[CoversClass(ClearSiteDataCommand::class)]
#[Group('helfi_platform_config')]
final class ClearSiteDataCommandsTest extends UnitTestCase {

  /**
   * The ClearSiteData mock.
   */
  private ClearSiteData&MockObject $clearSiteData;

  /**
   * The SymfonyStyle mock.
   */
  private SymfonyStyle&MockObject $io;

  /**
   * The system under test.
   */
  private ClearSiteDataCommand $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->clearSiteData = $this->createMock(ClearSiteData::class);
    $this->io = $this->createMock(SymfonyStyle::class);
    $this->sut = new ClearSiteDataCommand($this->clearSiteData);
  }

  /**
   * Tests disable invokes the service, reports success, and prints status.
   */
  public function testDisable(): void {
    $input = $this->createInput('disable');

    $this->clearSiteData
      ->expects($this->once())
      ->method('disable');

    $this->io->expects($this->once())
      ->method('success')
      ->with('"Clear-Site-Data"-header disabled successfully.');

    $this->clearSiteData->method('getActiveEnable')->willReturn(FALSE);

    $this->io->expects($this->once())
      ->method('title')
      ->with('Current "Clear-Site-Data"-header status:');
    $this->io->expects($this->once())
      ->method('listing')
      ->with(['Enabled: No']);

    $this->assertSame(Command::SUCCESS, ($this->sut)($input, $this->io));
  }

  /**
   * Tests status output when the header is disabled in config.
   */
  public function testStatusWhenDisabled(): void {
    $input = $this->createInput('status');

    $this->clearSiteData->method('getActiveEnable')->willReturn(FALSE);

    $this->io->expects($this->once())
      ->method('title')
      ->with('Current "Clear-Site-Data"-header status:');
    $this->io->expects($this->once())
      ->method('listing')
      ->with(['Enabled: No']);

    $this->assertSame(Command::SUCCESS, ($this->sut)($input, $this->io));
  }

  /**
   * Tests status output when the header is enabled and active.
   */
  public function testStatusWhenEnabled(): void {
    $input = $this->createInput('status');
    $expire = 1_700_000_000;
    $expectedDate = date('Y-m-d H:i:s', $expire);

    $this->clearSiteData->method('getActiveEnable')->willReturn(TRUE);
    $this->clearSiteData->method('getActiveDirectives')->willReturn(['cache', 'cookies']);
    $this->clearSiteData->method('getActiveExpireAfter')->willReturn($expire);
    $this->clearSiteData->method('isEnabled')->willReturn(TRUE);

    $this->io->expects($this->once())
      ->method('title')
      ->with('Current "Clear-Site-Data"-header status:');
    $this->io->expects($this->once())
      ->method('listing')
      ->with([
        'Enabled: Yes',
        'Directives: cache, cookies',
        sprintf('Expires after: %s', $expectedDate),
      ]);

    $this->assertSame(Command::SUCCESS, ($this->sut)($input, $this->io));
  }

  /**
   * Tests warning when config says enabled but the header is not active.
   */
  public function testStatusWarningWhenEnabledButInactive(): void {
    $input = $this->createInput('status');
    $expire = 1_700_000_000;
    $expectedDate = date('Y-m-d H:i:s', $expire);

    $this->clearSiteData->method('getActiveEnable')->willReturn(TRUE);
    $this->clearSiteData->method('getActiveDirectives')->willReturn(['cache']);
    $this->clearSiteData->method('getActiveExpireAfter')->willReturn($expire);
    $this->clearSiteData->method('isEnabled')->willReturn(FALSE);

    $this->io->expects($this->once())
      ->method('title')
      ->with('Current "Clear-Site-Data"-header status:');
    $this->io->expects($this->once())
      ->method('warning')
      ->with('"Clear-Site-Data"-header is enabled, but it might be inactive due to expired TTL or empty directives.');

    $this->io->expects($this->once())
      ->method('listing')
      ->with([
        'Enabled: Yes',
        'Directives: cache',
        sprintf('Expires after: %s', $expectedDate),
      ]);

    $this->assertSame(Command::SUCCESS, ($this->sut)($input, $this->io));
  }

  /**
   * Tests enable passes directives and TTL from input, then prints success and status.
   */
  public function testEnable(): void {
    $selected = ['cache', 'storage'];
    $ttl = 2;
    $input = $this->createInput('enable', $ttl);

    $this->clearSiteData
      ->expects($this->once())
      ->method('enable')
      ->with($selected, $ttl);

    $this->io->expects($this->once())
      ->method('success')
      ->with('"Clear-Site-Data"-header enabled successfully.');

    $expireAfter = 1_700_000_100;
    $this->clearSiteData->method('getActiveEnable')->willReturn(TRUE);
    $this->clearSiteData->method('getActiveDirectives')->willReturn($selected);
    $this->clearSiteData->method('getActiveExpireAfter')->willReturn($expireAfter);
    $this->clearSiteData->method('isEnabled')->willReturn(TRUE);

    $this->io->expects($this->once())
      ->method('title')
      ->with('Current "Clear-Site-Data"-header status:');
    $this->io->expects($this->once())
      ->method('listing')
      ->with([
        'Enabled: Yes',
        'Directives: cache, storage',
        sprintf('Expires after: %s', date('Y-m-d H:i:s', $expireAfter)),
      ]);

    $this->assertSame(
      Command::SUCCESS,
      ($this->sut)($input, $this->io, 'enable', 'cache,storage', $ttl),
    );
  }

  /**
   * Tests enable with no directives returns failure.
   */
  public function testEnableNoDirectives(): void {
    $input = $this->createInput('enable', 1);

    $this->io->expects($this->once())
      ->method('error')
      ->with(sprintf(
        'No directives provided. Possible values: %s.',
        implode(', ', ClearSiteData::VALID_DIRECTIVES),
      ));

    $this->clearSiteData->expects($this->never())->method('enable');

    $this->assertSame(
      Command::FAILURE,
      ($this->sut)($input, $this->io, 'enable', '', 1),
    );
  }

  /**
   * Tests invalid operation returns failure.
   */
  public function testInvalidOperation(): void {
    $input = $this->createInput('purge');

    $this->io->expects($this->once())
      ->method('error')
      ->with('Invalid operation: purge. Possible values: status, enable, disable.');

    $this->clearSiteData->expects($this->never())->method('disable');
    $this->clearSiteData->expects($this->never())->method('enable');

    $this->assertSame(Command::FAILURE, ($this->sut)($input, $this->io));
  }

  /**
   * Tests invalid directives return failure.
   */
  public function testEnableInvalidDirectives(): void {
    $input = $this->createInput('enable', 1);

    $this->io->expects($this->once())
      ->method('error')
      ->with(sprintf(
        'Invalid directives: not-a-directive. Possible values: %s.',
        implode(', ', ClearSiteData::VALID_DIRECTIVES),
      ));

    $this->clearSiteData->expects($this->never())->method('enable');

    $this->assertSame(
      Command::FAILURE,
      ($this->sut)($input, $this->io, 'enable', 'not-a-directive', 1),
    );
  }

  /**
   * Tests TTL below minimum returns failure.
   */
  public function testEnableTtlTooLow(): void {
    $input = $this->createInput('enable', ClearSiteData::MIN_EXPIRE_TIME - 1);

    $this->io->expects($this->once())
      ->method('error')
      ->with(sprintf(
        'Invalid TTL: %s. Must be between %s and %s hours.',
        ClearSiteData::MIN_EXPIRE_TIME - 1,
        ClearSiteData::MIN_EXPIRE_TIME,
        ClearSiteData::MAX_EXPIRE_TIME,
      ));

    $this->clearSiteData->expects($this->never())->method('enable');

    $this->assertSame(
      Command::FAILURE,
      ($this->sut)($input, $this->io, 'enable', 'cache', 1),
    );
  }

  /**
   * Tests TTL above maximum returns failure.
   */
  public function testEnableTtlTooHigh(): void {
    $input = $this->createInput('enable', ClearSiteData::MAX_EXPIRE_TIME + 1);

    $this->io->expects($this->once())
      ->method('error')
      ->with(sprintf(
        'Invalid TTL: %s. Must be between %s and %s hours.',
        ClearSiteData::MAX_EXPIRE_TIME + 1,
        ClearSiteData::MIN_EXPIRE_TIME,
        ClearSiteData::MAX_EXPIRE_TIME,
      ));

    $this->clearSiteData->expects($this->never())->method('enable');

    $this->assertSame(
      Command::FAILURE,
      ($this->sut)($input, $this->io, 'enable', 'cache', 1),
    );
  }

  /**
   * Builds an input mock with the given operation and TTL option.
   *
   * Directives are passed to the command via the __invoke argument, not input.
   */
  private function createInput(string $operation, int $ttl = 1) : InputInterface&MockObject {
    $input = $this->createMock(InputInterface::class);
    $input->method('getArgument')
      ->with('operation')
      ->willReturn($operation);
    $input->method('getOption')
      ->with('ttl')
      ->willReturn($ttl);
    return $input;
  }

}
