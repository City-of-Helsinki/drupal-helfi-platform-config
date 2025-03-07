<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_platform_config\Plugin\Block\SidebarContentBlock;
use Drupal\helfi_tpr\Entity\Service;
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
  private SidebarContentBlock|MockObject $lowerContentBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->lowerContentBlock = $this->getMockBuilder(SidebarContentBlock::class)
      ->setConstructorArgs([
        [],
        'sidebar_content_block',
        ['provider' => 'helfi_platform_config'],
        $this->entityTypeManager,
        $this->entityVersionMatcher,
        $this->moduleHandler,
      ])
      ->onlyMethods(['getCurrentEntityVersion'])
      ->getMock();

    $this->lowerContentBlock->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests that render array is empty when no entity is available.
   *
   * @covers ::build
   */
  public function testBuildReturnsDefaultRenderArray(): void {
    $this->lowerContentBlock->expects($this->once())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => NULL, 'entity_version' => NULL]);

    $expected = [
      'sidebar_content' => [
        '#theme' => 'sidebar_content_block',
        '#title' => $this->translate('Sidebar content block'),
      ],
    ];

    $this->assertEquals($expected, $this->lowerContentBlock->build());
  }

  /**
   * Tests that render array is built correctly with a valid entity.
   *
   * @covers ::build
   */
  public function testBuildIncludesServiceEntityRenderArray(): void {
    $serviceEntity = $this->createMock(Service::class);
    $entityViewBuilder = $this->createMock(EntityViewBuilderInterface::class);
    $computedViewArray = ['#markup' => 'Important links'];

    $entityViewBuilder->expects($this->once())
      ->method('view')
      ->with($serviceEntity)
      ->willReturn($computedViewArray);

    $this->entityTypeManager->expects($this->any())
      ->method('getViewBuilder')
      ->with('tpr_service')
      ->willReturn($entityViewBuilder);

    $this->lowerContentBlock->expects($this->once())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => $serviceEntity, 'entity_version' => NULL]);

    $expected = [
      'sidebar_content' => [
        '#theme' => 'sidebar_content_block',
        '#title' => $this->translate('Sidebar content block'),
        '#computed' => [
          '#markup' => 'Important links',
          '#theme' => 'tpr_service_important_links',
        ],
      ],
    ];

    $this->assertEquals($expected, $this->lowerContentBlock->build());
  }

  /**
   * Tests that render array includes paragraphs and cache tags.
   *
   * @covers ::build
   */
  public function testBuildIncludesParagraphsAndCacheTags(): void {
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $fieldSidebarContent = $this->createMock(FieldItemListInterface::class);

    $contentEntity->expects($this->once())
      ->method('hasField')
      ->with('field_sidebar_content')
      ->willReturn(TRUE);

    $contentEntity->expects($this->once())
      ->method('get')
      ->with('field_sidebar_content')
      ->willReturn($fieldSidebarContent);

    $contentEntity->expects($this->once())
      ->method('getCacheTags')
      ->willReturn(['entity:node:1']);

    $this->lowerContentBlock->expects($this->once())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => $contentEntity, 'entity_version' => EntityVersionMatcher::ENTITY_VERSION_REVISION]);

    $expected = [
      'sidebar_content' => [
        '#theme' => 'sidebar_content_block',
        '#title' => $this->translate('Sidebar content block'),
        '#is_revision' => TRUE,
        '#paragraphs' => $fieldSidebarContent,
        '#cache' => [
          'tags' => ['entity:node:1'],
        ],
      ],
    ];

    $this->assertEquals($expected, $this->lowerContentBlock->build());
  }

}
