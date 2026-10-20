<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Controller;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_platform_config\Controller\ContentCardsHtmxController;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the content cards HTMX controller.
 */
#[CoversClass(ContentCardsHtmxController::class)]
#[Group('helfi_platform_config')]
final class ContentCardsHtmxControllerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'paragraphs',
    'entity_reference_revisions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['field', 'node', 'paragraphs']);

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    ParagraphsType::create([
      'id' => 'content_cards',
      'label' => 'Content cards',
    ])->save();

    ParagraphsType::create([
      'id' => 'text',
      'label' => 'Text',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_content_cards_content_ext',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'node',
      ],
      'cardinality' => -1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_content_cards_content_ext',
      'entity_type' => 'paragraph',
      'bundle' => 'content_cards',
      'label' => 'Content',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['page' => 'page'],
        ],
      ],
    ])->save();

    $this->setUpCurrentUser(permissions: ['access content']);
  }

  /**
   * A content cards paragraph is rendered with the teaser entity formatter.
   */
  public function testRendersTeaserCards() : void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Parent',
    ]);
    $node->save();

    $card = Node::create([
      'type' => 'page',
      'title' => 'Card',
    ]);
    $card->save();

    $paragraph = Paragraph::create([
      'type' => 'content_cards',
      'field_content_cards_content_ext' => [
        'target_id' => $card->id(),
      ],
    ]);
    $paragraph->setParentEntity($node, 'field_paragraphs');
    $paragraph->save();

    $build = $this->controller()->content($paragraph);

    $this->assertSame(['languages:language_content'], $build['#cache']['contexts']);
    $this->assertContains('paragraph:' . $paragraph->id(), $build['#cache']['tags']);
    $this->assertContains('node:' . $node->id(), $build['#cache']['tags']);
    $this->assertSame('entity_reference_entity_view', $build['cards']['#formatter']);
    $this->assertSame('hidden', $build['cards']['#label_display']);
    $this->assertSame($card->id(), $build['cards'][0]['#node']->id());
  }

  /**
   * Other paragraph bundles are not rendered.
   */
  public function testOtherBundlesAreEmpty() : void {
    $paragraph = Paragraph::create([
      'type' => 'text',
    ]);
    $paragraph->save();

    $this->assertSame([], $this->controller()->content($paragraph));
  }

  /**
   * Builds the controller from the container.
   */
  private function controller() : ContentCardsHtmxController {
    return ContentCardsHtmxController::create($this->container);
  }

}
