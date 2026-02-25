<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Drush\Commands;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_platform_config\Drush\Commands\TextConverterCommands;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorage;
use Drupal\Tests\UnitTestCase;
use Drush\Commands\DrushCommands;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests for TextConverterCommands.
 */
class TextConverterCommandsTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Make sure preview command exits gracefully when entity type is not found.
   */
  public function testPreviewInvalidEntityType() : void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')
      ->shouldBeCalled()
      ->willThrow(new PluginNotFoundException('node'));
    $sut = $this->getSut(entityTypeManager: $entityTypeManager->reveal());
    $output = $this->prophesize(OutputInterface::class);

    $result = $sut($output->reveal(), 'node', '1');
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
    $sut = $this->getSut(entityTypeManager: $entityTypeManager->reveal());
    $output = $this->prophesize(OutputInterface::class);
    $output->writeln('Entity node:1 not found.')->shouldBeCalled();

    $result = $sut($output->reveal(), 'node', '1');
    $this->assertEquals(DrushCommands::EXIT_FAILURE, $result);
  }

  /**
   * Make sure process command exits gracefully if text converter is not found.
   */
  public function testPreviewNotSupported() : void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $node = $this->prophesize(Node::class);
    $nodeStorage = $this->prophesize(NodeStorage::class);
    $nodeStorage->load('1')
      ->shouldBeCalled()
      ->willReturn($node->reveal());
    $entityTypeManager->getStorage('node')->willReturn($nodeStorage->reveal());
    $sut = $this->getSut(entityTypeManager: $entityTypeManager->reveal());
    $output = $this->prophesize(OutputInterface::class);
    $output->writeln('Failed to find text converter for node:1')->shouldBeCalled();

    $result = $sut($output->reveal(), 'node', '1');
    $this->assertEquals(DrushCommands::EXIT_FAILURE, $result);
  }

  /**
   * Gets the SUT.
   */
  private function getSut(
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?TextConverterManager $textConverter = NULL,
  ) : TextConverterCommands {
    if (!$entityTypeManager) {
      $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class)->reveal();
    }
    if (!$textConverter) {
      $textConverter = new TextConverterManager();
    }

    $logger = $this->prophesize(LoggerInterface::class);

    return new TextConverterCommands($entityTypeManager, $textConverter, $logger->reveal());
  }

}
