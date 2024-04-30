<?php

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the hero block access.
 *
 * @group helfi_platform_config
 */
class HeroBlockTest extends EntityKernelTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'block',
    'config_rewrite',
    'helfi_platform_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('block');
  }

  /**
   * Test block access.
   *
   * @dataProvider getTestNodes
   */
  public function testBlockAccess(array $values, bool $showHero) {
    $hero = Block::create([
      'id' => $this->randomMachineName(),
      'plugin' => 'hero_block',
      'region' => 'content',
      'theme' => 'stark',
      'weight' => 99,
    ]);

    $title = Block::create([
      'id' => $this->randomMachineName(),
      'plugin' => 'page_title_block',
      'region' => 'content',
      'theme' => 'stark',
      'weight' => 100,
    ]);

    $node = $this->mockNode($values);

    $versionMatcher = $this->prophesize(EntityVersionMatcher::class);
    $versionMatcher->getType()->willReturn(
      ['entity' => $node, 'entity_version' => 'canonical'],
    );
    $this->container->set('helfi_platform_config.entity_version_matcher', $versionMatcher->reveal());

    $titleAccess = $title->access('view', NULL, TRUE);
    $heroAccess = $hero->access('view', NULL, TRUE);

    $this->assertTrue($showHero ? $titleAccess->isForbidden() : $titleAccess->isAllowed());
    $this->assertTrue($showHero ? $heroAccess->isAllowed() : $heroAccess->isForbidden());
  }

  /**
   * Data provider for tests.
   *
   * @return array[]
   *   The data.
   */
  public function getTestNodes() : array {
    return [
      [
        // Has no hero field.
        [],
        FALSE,
      ],
      [
        // Hero hidden.
        [
          'field_has_hero' => FALSE,
          'field_hero' => NULL,
        ],
        FALSE,
      ],
      [
        // Hero visible but field value is missing.
        [
          'field_has_hero' => TRUE,
          'field_hero' => NULL,
        ],
        FALSE,
      ],
      [
        // Hero visible and evaluates to TRUE.
        [
          'field_has_hero' => TRUE,
          'field_hero' => TRUE,
        ],
        TRUE,
      ],
      [
        // Hero evaluates to TRUE but is hidden.
        [
          'field_has_hero' => FALSE,
          'field_hero' => TRUE,
        ],
        FALSE,
      ],
    ];
  }

  /**
   * Create mocked node.
   */
  private function mockNode(array $values): NodeInterface {
    $node = $this->getMockBuilder(Node::class)
      ->disableOriginalConstructor()
      ->getMock();

    $fields = [];

    foreach ($values as $key => $value) {
      $field = $this
        ->getMockBuilder(FieldItemListInterface::class)
        ->disableOriginalConstructor()
        ->getMock();

      $field
        ->expects($this->any())
        ->method('__get')
        ->willReturnCallback(function ($property) use ($value) {
          return match ($property) {
            'value', 'entity' => $value,
            default => throw new \LogicException(),
          };
        });

      $fields[$key] = $field;
    }

    $get_callback = function ($property) use ($fields) {
      if (array_key_exists($property, $fields)) {
        return $fields[$property];
      }

      throw new \LogicException();
    };

    $node
      ->expects($this->any())
      ->method('__get')
      ->willReturnCallback($get_callback);

    $node
      ->expects($this->any())
      ->method('get')
      ->willReturnCallback($get_callback);

    $node
      ->expects($this->any())
      ->method('hasField')
      ->willReturnCallback(fn ($property) => array_key_exists($property, $fields));

    return $node;
  }

}
