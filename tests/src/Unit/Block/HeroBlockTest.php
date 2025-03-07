<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_platform_config\Plugin\Block\HeroBlock;
use Drupal\paragraphs\ParagraphInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\HeroBlock
 *
 * @group helfi_platform_config
 */
class HeroBlockTest extends BlockUnitTestBase {

  /**
   * The tested block.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\HeroBlock|\PHPUnit\Framework\MockObject\MockObject
   */
  private HeroBlock|MockObject $heroBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->heroBlock = $this->getMockBuilder(HeroBlock::class)
      ->setConstructorArgs([
        [],
        'hero_block',
        ['provider' => 'helfi_platform_config'],
        $this->entityTypeManager,
        $this->entityVersionMatcher,
        $this->moduleHandler,
      ])
      ->onlyMethods(['getCurrentEntityVersion'])
      ->getMock();

    $this->heroBlock->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests that render array is empty when no entity is available.
   *
   * @covers ::build
   */
  public function testBuildReturnsEmptyArrayWhenNoEntity(): void {
    $this->heroBlock->expects($this->once())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => NULL, 'entity_version' => NULL]);

    $this->assertSame([], $this->heroBlock->build());
  }

  /**
   * Tests that render array is empty when the entity has no hero field.
   *
   * @covers ::build
   */
  public function testBuildReturnsEmptyArrayWhenEntityHasNoHeroField(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $this->createMockedEntity($entity, TRUE, TRUE);

    $this->heroBlock->expects($this->once())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => $entity, 'entity_version' => NULL]);

    $this->assertSame([], $this->heroBlock->build());
  }

  /**
   * Tests that render array is built correctly with a valid entity.
   *
   * @covers ::build
   */
  public function testBuildReturnsCorrectRenderArray(): void {
    $entity = $this->createMock(ContentEntityInterface::class);

    $this->createMockedEntity($entity, empty_content: TRUE);

    $this->heroBlock->expects($this->any())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => $entity, 'entity_version' => EntityVersionMatcher::ENTITY_VERSION_REVISION]);

    $expected = [
      'hero_block' => [
        '#theme' => 'hero_block',
        '#title' => new TranslatableMarkup('Hero block', string_translation: $this->stringTranslation),
        '#paragraphs' => $this->createMock(FieldItemListInterface::class),
        '#is_revision' => TRUE,
        '#first_paragraph_grey' => '',
        '#cache' => [
          'tags' => ['entity:node:1'],
        ],
      ],
    ];

    $this->assertEquals($expected, $this->heroBlock->build());
  }

  /**
   * Tests that render array has the first paragraph gray value.
   *
   * @covers ::build
   */
  public function testBuildAddsFirstParagraphGreyClass(): void {
    $entity = $this->createMock(ContentEntityInterface::class);

    $this->createMockedEntity($entity);

    $entity->expects($this->any())
      ->method('bundle')
      ->willReturn('landing_page');

    $this->heroBlock->expects($this->once())
      ->method('getCurrentEntityVersion')
      ->willReturn(['entity' => $entity, 'entity_version' => EntityVersionMatcher::ENTITY_VERSION_CANONICAL]);

    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('first_paragraph_grey', ['unit_search', 'service_list_search']);

    $expected = [
      'hero_block' => [
        '#theme' => 'hero_block',
        '#title' => $this->translate('Hero block'),
        '#paragraphs' => $this->createMock(FieldItemListInterface::class),
        '#is_revision' => FALSE,
        '#first_paragraph_grey' => 'has-first-gray-bg-block',
        '#cache' => [
          'tags' => ['entity:node:1'],
        ],
      ],
    ];

    $this->assertEquals($expected, $this->heroBlock->build());
  }

  /**
   * Create entity.
   *
   * @param \PHPUnit\Framework\MockObject\MockObject $entity
   *   Mocked entity.
   * @param bool $empty_hero
   *   Set to true, if Hero is empty.
   * @param bool $empty_content
   *   Set to true, if content is empty.
   */
  private function createMockedEntity(MockObject &$entity, bool $empty_hero = FALSE, bool $empty_content = FALSE): void {
    $entity->expects($this->any())
      ->method('hasField')
      ->willReturnMap([
        ['field_hero', !$empty_hero],
        ['field_has_hero', !$empty_hero],
        ['field_content', !$empty_content],
      ]);

    // Mock field_has_hero to return a field item list object.
    $field_has_hero = $this->createMock(FieldItemListInterface::class);
    $field_has_hero->method('isEmpty')->willReturn($empty_hero);

    // Mock field_content to return a field item list containing the paragraph.
    $field_content = $this->createMock(FieldItemListInterface::class);
    $field_content->method('isEmpty')->willReturn($empty_content);

    // If field content is set, mock paragraph.
    if (!$empty_content) {
      $paragraph = $this->createMock(ParagraphInterface::class);
      $paragraph->expects($this->any())
        ->method('getType')
        ->willReturn('unit_search');
      $field_content->method('__get')->with('entity')->willReturn($paragraph);
    }

    $entity->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['field_hero', $this->createMock(FieldItemListInterface::class)],
        ['field_has_hero', $field_has_hero],
        ['field_content', $field_content],
      ]);
    $entity->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['field_hero', $this->createMock(FieldItemListInterface::class)],
        ['field_has_hero', $field_has_hero],
        ['field_content', $field_content],
      ]);

    $entity->expects($this->any())
      ->method('getCacheTags')
      ->willReturn(['entity:node:1']);
  }

}
