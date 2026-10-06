<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\helfi_platform_config\Plugin\Block\SidebarContentBlock;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\SidebarContentBlock
 *
 * @group helfi_platform_config
 */
class SidebarContentBlockTest extends BlockUnitTestBase {

  /**
   * The tested block.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\SidebarContentBlock|\PHPUnit\Framework\MockObject\MockObject
   */
  private SidebarContentBlock|MockObject $sidebarContentBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->sidebarContentBlock = $this->getMockBuilder(SidebarContentBlock::class)
      ->setConstructorArgs([
        [],
        'sidebar_content_block',
        ['provider' => 'helfi_platform_config'],
        $this->entityTypeManager,
        $this->entityVersionMatcher,
        $this->moduleHandler,
      ])
      ->onlyMethods([])
      ->getMock();

    $this->sidebarContentBlock->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests that render array contains the sidebar content theme.
   *
   * @covers ::build
   */
  public function testBuildReturnsDefaultRenderArray(): void {
    $expected = [
      'sidebar_content' => [
        '#theme' => 'sidebar_content_block',
        '#title' => $this->translate('Sidebar content block'),
      ],
    ];

    $this->assertEquals($expected, $this->sidebarContentBlock->build());
  }

}
