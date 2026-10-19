<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\helfi_platform_config\Controller\ContentCardsHtmxController;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Tests the content cards HTMX controller.
 */
#[Group('helfi_platform_config')]
final class ContentCardsHtmxControllerTest extends UnitTestCase {

  /**
   * A content cards paragraph is rendered with the teaser entity formatter.
   */
  public function testRendersTeaserCards() : void {
    $field = $this->prophesize(FieldItemListInterface::class)->reveal();
    $parent = $this->prophesize(EntityInterface::class)->reveal();

    $paragraph = $this->prophesize(ParagraphInterface::class);
    $paragraph->bundle()->willReturn('content_cards');
    $paragraph->hasField('field_content_cards_content_ext')->willReturn(TRUE);
    $paragraph->get('field_content_cards_content_ext')->willReturn($field);
    $paragraph->getParentEntity()->willReturn($parent);

    $view_builder = $this->prophesize(EntityViewBuilderInterface::class);
    $view_builder->viewField($field, [
      'type' => 'entity_reference_entity_view',
      'label' => 'hidden',
      'settings' => [
        'view_mode' => 'teaser',
        'link' => FALSE,
      ],
    ])->willReturn(['#markup' => 'cards']);

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getViewBuilder('paragraph')->willReturn($view_builder->reveal());

    $renderer = $this->createMock(RendererInterface::class);
    $renderer->expects($this->exactly(2))->method('addCacheableDependency');

    $build = (new ContentCardsHtmxController($entity_type_manager->reveal(), $renderer))->content($paragraph->reveal());

    $this->assertSame(['languages:language_content'], $build['#cache']['contexts']);
    $this->assertSame(['#markup' => 'cards'], $build['cards']);
  }

  /**
   * Other paragraph bundles are not rendered.
   */
  public function testOtherBundlesAreEmpty() : void {
    $paragraph = $this->prophesize(ParagraphInterface::class);
    $paragraph->bundle()->willReturn('text');
    $paragraph->hasField(Argument::any())->shouldNotBeCalled();

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getViewBuilder(Argument::any())->shouldNotBeCalled();

    $renderer = $this->createMock(RendererInterface::class);
    $renderer->expects($this->never())->method('addCacheableDependency');

    $build = (new ContentCardsHtmxController($entity_type_manager->reveal(), $renderer))->content($paragraph->reveal());

    $this->assertSame([], $build);
  }

}
