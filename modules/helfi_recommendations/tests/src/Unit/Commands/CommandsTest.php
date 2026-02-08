<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_recommendations\Drush\Commands\Commands;
use Drupal\helfi_recommendations\ReferenceUpdater;
use Drupal\helfi_recommendations\TopicsManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drush\Commands\DrushCommands;
use Drush\Style\DrushStyle;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Unit tests for Drush commands.
 *
 * @coversDefaultClass \Drupal\helfi_recommendations\Drush\Commands\Commands
 */
#[Group('helfi_recommendations')]
class CommandsTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Gets the SUT.
   */
  private function getSut(
    ?Connection $connection = NULL,
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?TopicsManagerInterface $topicsManager = NULL,
    ?ReferenceUpdater $referenceUpdater = NULL,
    ?ObjectProphecy $io = NULL,
  ) : Commands {
    if (!$connection) {
      $connection = $this->prophesize(Connection::class)->reveal();
    }
    if (!$entityTypeManager) {
      $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class)->reveal();
    }
    if (!$topicsManager) {
      $topicsManager = $this->prophesize(TopicsManagerInterface::class)->reveal();
    }
    if (!$referenceUpdater) {
      $referenceUpdater = $this->prophesize(ReferenceUpdater::class)->reveal();
    }
    $sut = new Commands($connection, $entityTypeManager, $topicsManager, $referenceUpdater);

    if (!$io) {
      $io = $this->prophesize(DrushStyle::class);
    }
    $output = $this->prophesize(OutputInterface::class);
    $input = $this->prophesize(InputInterface::class);
    $sut->restoreState($input->reveal(), $output->reveal(), $io->reveal());
    return $sut;
  }

  /**
   * Make sure process command exits gracefully when entity type is not found.
   */
  public function testProcessEntityDefinitionNotFound() : void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getDefinition('node')
      ->shouldBeCalled()
      ->willReturn(NULL);
    $io = $this->prophesize(DrushStyle::class);
    $io->writeln('Given entity type is not supported.')->shouldBeCalled();

    $result = $this->getSut(entityTypeManager: $entityTypeManager->reveal(), io: $io)
      ->process('node', 'page');
    $this->assertEquals(DrushCommands::EXIT_FAILURE, $result);
  }

}
