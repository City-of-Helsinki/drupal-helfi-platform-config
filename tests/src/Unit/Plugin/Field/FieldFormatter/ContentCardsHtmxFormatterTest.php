<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\Field\FieldFormatter;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\helfi_platform_config\Plugin\Field\FieldFormatter\ContentCardsHtmxFormatter;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the content cards HTMX placeholder.
 */
#[Group('helfi_platform_config')]
final class ContentCardsHtmxFormatterTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $generated = new GeneratedUrl();
    $generated->setGeneratedUrl('/content-cards/12/htmx');
    $generator = $this->createMock(UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturnCallback(function (string $name, array $parameters, array $options, bool $collect) use ($generated) {
      return $collect ? $generated : $generated->getGeneratedUrl();
    });

    $container = new ContainerBuilder();
    $container->set('url_generator', $generator);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * The placeholder counts stored ids and does not load the targets.
   */
  public function testPlaceholderSkipsReferencedEntities() : void {
    $paragraph = $this->prophesize(ParagraphInterface::class);
    $paragraph->id()->willReturn('12');

    $items = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $items->isEmpty()->willReturn(FALSE);
    $items->count()->willReturn(2);
    $items->getEntity()->willReturn($paragraph->reveal());
    $items->referencedEntities()->shouldNotBeCalled();

    $elements = $this->formatter()->viewElements($items->reveal(), 'fi');

    $this->assertCount(1, $elements);
    $this->assertSame('helfi_htmx_preview__content_cards', $elements[0]['#theme']);
    $this->assertSame(2, $elements[0]['#num_items']);
    $this->assertContains('core/drupal.htmx', $elements[0]['#attached']['library']);
    $this->assertSame('load', $this->attribute($elements[0], 'data-hx-trigger'));
    $this->assertSame('/content-cards/12/htmx', $this->attribute($elements[0], 'data-hx-get'));
    $this->assertStringStartsWith('innerHTML', (string) $this->attribute($elements[0], 'data-hx-swap'));
  }

  /**
   * An empty field renders nothing.
   */
  public function testEmptyFieldRendersNothing() : void {
    $items = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $items->isEmpty()->willReturn(TRUE);
    $items->referencedEntities()->shouldNotBeCalled();
    $items->getEntity()->shouldNotBeCalled();

    $this->assertSame([], $this->formatter()->viewElements($items->reveal(), 'fi'));
  }

  /**
   * Builds the formatter.
   */
  private function formatter() : ContentCardsHtmxFormatter {
    return new ContentCardsHtmxFormatter(
      'content_cards_htmx',
      [],
      $this->prophesize(FieldDefinitionInterface::class)->reveal(),
      [],
      'hidden',
      'default',
      [],
    );
  }

  /**
   * Reads one HTMX attribute from a placeholder render element.
   */
  private function attribute(array $element, string $name) : string {
    $attributes = $element['#attributes'];
    return (string) $attributes[$name];
  }

}
