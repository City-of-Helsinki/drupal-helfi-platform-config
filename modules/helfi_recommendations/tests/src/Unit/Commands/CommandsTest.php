<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Unit\Commands;

use DG\BypassFinals;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_recommendations\Drush\Commands\Commands;
use Drupal\helfi_recommendations\ReferenceUpdater;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\helfi_recommendations\TopicsManager;
use Drupal\node\NodeStorage;
use Drupal\Tests\UnitTestCase;
use Drush\Commands\DrushCommands;
use Drush\Log\DrushLoggerManager;
use Drush\Style\DrushStyle;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Unit tests for Drush commands.
 *
 * @group helfi_recommendations
 * @coversDefaultClass \Drupal\helfi_recommendations\Drush\Commands\Commands
 */
class CommandsTest extends UnitTestCase {

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
   * @param \Drupal\Core\Database\Connection|null $connection
   *   The connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\helfi_platform_config\TextConverter\TextConverterManager|null $textConverter
   *   The text converter.
   * @param \Drupal\helfi_recommendations\TopicsManager|null $topicsManager
   *   The topics manager.
   * @param \Drupal\helfi_recommendations\ReferenceUpdater|null $referenceUpdater
   *   The reference updated.
   * @param \Prophecy\Prophecy\ObjectProphecy|null $io
   *   The IO prophecy.
   *
   * @return \Drupal\helfi_recommendations\Drush\Commands\Commands
   *   The SUT.
   */
  private function getSut(
    ?Connection $connection = NULL,
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?TextConverterManager $textConverter = NULL,
    ?TopicsManager $topicsManager = NULL,
    ?ReferenceUpdater $referenceUpdater = NULL,
    ?ObjectProphecy $io = NULL,
  ) : Commands {
    if (!$connection) {
      $connection = $this->prophesize(Connection::class)->reveal();
    }
    if (!$entityTypeManager) {
      $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class)->reveal();
    }
    if (!$textConverter) {
      $textConverter = new TextConverterManager();
    }
    if (!$topicsManager) {
      $topicsManager = $this->prophesize(TopicsManager::class)->reveal();
    }
    if (!$referenceUpdater) {
      $referenceUpdater = $this->prophesize(ReferenceUpdater::class)->reveal();
    }
    $sut = new Commands($connection, $entityTypeManager, $textConverter, $topicsManager, $referenceUpdater);

    if (!$io) {
      $io = $this->prophesize(DrushStyle::class);
    }
    $output = $this->prophesize(OutputInterface::class);
    $input = $this->prophesize(InputInterface::class);
    $sut->restoreState($input->reveal(), $output->reveal(), $io->reveal());
    return $sut;
  }

  /**
   * Make sure preview command exits gracefully when entity type is not found.
   */
  public function testPreviewInvalidEntityType() : void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')
      ->shouldBeCalled()
      ->willThrow(new PluginNotFoundException('node'));
    $sut = $this->getSut(entityTypeManager: $entityTypeManager->reveal());
    $sut->setLogger($this->prophesize(DrushLoggerManager::class)->reveal());

    $result = $sut->preview('node', '1');
    $this->assertEquals(DrushCommands::EXIT_FAILURE, $result);
  }

  /**
   * Make sure preview command exits gracefully when entity is not found.
   */
  public function testPreviewEntityNotFound() : void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $nodeStorage = $this->prophesize(NodeStorage::class);
    $nodeStorage->load('1')
      ->shouldBeCalled()
      ->willReturn(NULL);
    $entityTypeManager->getStorage('node')->willReturn($nodeStorage->reveal());
    $io = $this->prophesize(DrushStyle::class);
    $io->error('Failed to load node:1')->shouldBeCalled();

    $result = $this->getSut(entityTypeManager: $entityTypeManager->reveal(), io: $io)
      ->preview('node', '1');
    $this->assertEquals(DrushCommands::EXIT_FAILURE, $result);
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
