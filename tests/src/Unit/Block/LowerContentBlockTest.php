<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_platform_config\Plugin\Block\LowerContentBlock;
use Drupal\helfi_tpr\Entity\Service;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\LowerContentBlock
 *
 * @group helfi_platform_config
 */
class LowerContentBlockTest extends BlockUnitTestBase {

  /**
   * The tested block.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\LowerContentBlock|\PHPUnit\Framework\MockObject\MockObject
   */
  private LowerContentBlock|MockObject $lowerContentBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->lowerContentBlock = $this->getMockBuilder(LowerContentBlock::class)
      ->setConstructorArgs([
        [],
        'lower_content_block',
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
   * Tests that render array is built correctly with a valid entity.
   *
   * @covers ::build
   */
  public function testBuildReturnsDefaultRenderArray(): void {
    $this->lowerContentBlock->expects($this->once())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => NULL, 'entity_version' => NULL]);

    $expected = [
      'lower_content' => [
        '#theme' => 'lower_content_block',
        '#title' => $this->translate('Lower content block'),
      ],
    ];

    $this->assertEquals($expected, $this->lowerContentBlock->build());
  }

  /**
   * Tests that build() includes service entity render data.
   *
   * @covers ::build
   */
  public function testBuildIncludesServiceEntityRenderArray(): void {
    $serviceEntity = $this->createMock(Service::class);
    $entityViewBuilder = $this->createMock(EntityViewBuilderInterface::class);
    $computedViewArray = ['#markup' => 'Rendered service'];

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
      'lower_content' => [
        '#theme' => 'lower_content_block',
        '#title' => $this->translate('Lower content block'),
        '#computed' => [
          '#markup' => 'Rendered service',
          '#theme' => 'tpr_service_lower_content',
        ],
      ],
    ];

    $this->assertEquals($expected, $this->lowerContentBlock->build());
  }

  /**
   * Tests that build() includes paragraphs and cache tags.
   *
   * @covers ::build
   */
  public function testBuildIncludesParagraphsAndCacheTags(): void {
    $contentEntity = $this->createMock(ContentEntityInterface::class);
    $fieldLowerContent = $this->createMock(FieldItemListInterface::class);

    $contentEntity->expects($this->once())
      ->method('hasField')
      ->with('field_lower_content')
      ->willReturn(TRUE);

    $contentEntity->expects($this->once())
      ->method('get')
      ->with('field_lower_content')
      ->willReturn($fieldLowerContent);

    $contentEntity->expects($this->once())
      ->method('getCacheTags')
      ->willReturn(['entity:node:1']);

    $this->lowerContentBlock->expects($this->once())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => $contentEntity, 'entity_version' => EntityVersionMatcher::ENTITY_VERSION_REVISION]);

    $expected = [
      'lower_content' => [
        '#theme' => 'lower_content_block',
        '#title' => $this->translate('Lower content block'),
        '#is_revision' => TRUE,
        '#paragraphs' => $fieldLowerContent,
        '#cache' => [
          'tags' => ['entity:node:1'],
        ],
      ],
    ];

    $this->assertEquals($expected, $this->lowerContentBlock->build());
  }

}
