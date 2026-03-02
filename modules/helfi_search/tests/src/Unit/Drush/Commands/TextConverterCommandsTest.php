<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Drush\Commands;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_search\Drush\Commands\TextPipelineCommands;
use Drupal\helfi_search\Pipeline\TextPipeline;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorage;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Tests for TextPipelineCommands.
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
    $input = $this->prophesize(InputInterface::class);
    $output = $this->prophesize(OutputInterface::class);

    $result = $sut($input->reveal(), $output->reveal(), 'node', '1');
    $this->assertEquals(Command::FAILURE, $result);
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
    $input = $this->prophesize(InputInterface::class);
    $output = $this->prophesize(OutputInterface::class);
    $output->writeln('Entity node:1 not found.')->shouldBeCalled();

    $result = $sut($input->reveal(), $output->reveal(), 'node', '1');
    $this->assertEquals(Command::FAILURE, $result);
  }

  /**
   * Make sure command exits gracefully when pipeline returns no results.
   */
  public function testPreviewNotSupported() : void {
    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $node = $this->prophesize(Node::class);
    $nodeStorage = $this->prophesize(NodeStorage::class);
    $nodeStorage->load('1')
      ->shouldBeCalled()
      ->willReturn($node->reveal());
    $entityTypeManager->getStorage('node')->willReturn($nodeStorage->reveal());

    $textPipeline = $this->prophesize(TextPipeline::class);
    $textPipeline->processEntities([$node->reveal()])
      ->willReturn([]);

    $sut = $this->getSut(
      entityTypeManager: $entityTypeManager->reveal(),
      textPipeline: $textPipeline->reveal(),
    );
    $input = $this->prophesize(InputInterface::class);
    $output = $this->prophesize(OutputInterface::class);
    $output->writeln('Failed to find text converter for node:1')->shouldBeCalled();

    $result = $sut($input->reveal(), $output->reveal(), 'node', '1');
    $this->assertEquals(Command::FAILURE, $result);
  }

  /**
   * Gets the SUT.
   */
  private function getSut(
    ?EntityTypeManagerInterface $entityTypeManager = NULL,
    ?TextPipeline $textPipeline = NULL,
  ) : TextPipelineCommands {
    if (!$entityTypeManager) {
      $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class)->reveal();
    }

    if (!$textPipeline) {
      $textPipeline = $this->prophesize(TextPipeline::class)->reveal();
    }

    $logger = $this->prophesize(LoggerInterface::class);

    return new TextPipelineCommands($entityTypeManager, $textPipeline, $logger->reveal());
  }

}
