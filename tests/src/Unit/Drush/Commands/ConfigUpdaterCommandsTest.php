<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use DG\BypassFinals;
use Drupal\Core\Extension\Extension;
use Drupal\config_rewrite\ConfigRewriterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdater;
use Drupal\helfi_platform_config\Drush\Commands\ConfigUpdaterCommands;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Unit tests for Drush commands.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\Drush\Commands\ConfigUpdaterCommands
 */
class ConfigUpdaterCommandsTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    BypassFinals::enable();
  }

  /**
   * Gets the SUT.
   *
   * @return \Drupal\helfi_platform_config\Drush\Commands\ConfigUpdaterCommands
   *   The SUT.
   */
  private function getSut(
    ?ConfigUpdater $configUpdater = NULL,
    ?ConfigRewriterInterface $configRewriter = NULL,
    ?ModuleHandlerInterface $moduleHandler = NULL,
  ) : ConfigUpdaterCommands {
    if (!$configUpdater) {
      $configUpdater = $this->prophesize(ConfigUpdater::class)->reveal();
    }
    if (!$configRewriter) {
      $configRewriter = $this->prophesize(ConfigRewriterInterface::class)->reveal();
    }
    if (!$moduleHandler) {
      $moduleHandler = $this->prophesize(ModuleHandlerInterface::class)->reveal();
    }
    $sut = new ConfigUpdaterCommands($configUpdater, $configRewriter, $moduleHandler);

    $output = $this->prophesize(OutputInterface::class);
    $input = $this->prophesize(InputInterface::class);
    $sut->restoreState($input->reveal(), $output->reveal());
    return $sut;
  }

  /**
   * Tests the update command.
   */
  public function testUpdate() : void {
    $module = $this->prophesize(Extension::class);
    $module->getPath()
      ->shouldBeCalled()
      ->willReturn(DRUPAL_ROOT . '/modules/contrib/helfi_platform_config');

    $moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
    $moduleHandler->moduleExists(Argument::any())
      ->shouldBeCalled()
      ->willReturn(TRUE);
    $moduleHandler->getModule('helfi_platform_config')
      ->shouldBeCalled()
      ->willReturn($module->reveal());

    $configUpdater = $this->prophesize(ConfigUpdater::class);
    $configUpdater->update('helfi_platform_config')
      ->shouldBeCalled();
    $configUpdater->update(Argument::any())
      ->shouldBeCalled();

    $sut = $this->getSut(
      configUpdater: $configUpdater->reveal(),
      moduleHandler: $moduleHandler->reveal(),
    );
    $sut->update();
  }

}
