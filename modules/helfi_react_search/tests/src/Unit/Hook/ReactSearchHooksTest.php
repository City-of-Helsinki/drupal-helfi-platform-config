<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_react_search\Unit\Hook;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormState;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\helfi_react_search\Hook\ReactSearchHooks;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\UnitTestCase;

/**
 * ReactSearch -hook class tests.
 */
final class ReactSearchHooksTest extends UnitTestCase {

  /**
   * Test hook_preprocess_paragraph().
   */
  public function testPreprocessParagraph(): void {
    $elasticProxyConfig = $this->prophesize(ImmutableConfig::class);
    $elasticProxyConfig->get('elastic_proxy_url')->willReturn('anything');

    $reactSearchConfig = $this->prophesize(ImmutableConfig::class);
    $reactSearchConfig->get('sentry_dsn_react')->willReturn('anything');

    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([
        'elastic_proxy.settings' => ['elastic_proxy_url' => 'anything1'],
        'react_search.settings' => ['sentry_dsn_react' => 'anything2'],
      ])
    );

    $badParagraph = $this->prophesize(Paragraph::class);
    $badParagraph->getType()->willReturn('accordion');

    $variables['paragraph'] = $badParagraph->reveal();
    $reactHooksClass->preprocessParagraph($variables);

    $this->assertFalse(
      isset($variables['#attached']['drupalSettings']['helfi_react_search']['elastic_proxy_url']),
      'Elastic proxy url should not be set.'
    );
    $this->assertFalse(
      isset($variables['#attached']['drupalSettings']['helfi_react_search']['sentry_dsn_react']),
      'Sentry dsn react should not be set.'
    );

    $goodParagraph = $this->prophesize(Paragraph::class);
    $goodParagraph->getType()->willReturn('event_list');

    $variables['paragraph'] = $goodParagraph->reveal();
    $reactHooksClass->preprocessParagraph($variables);

    $this->assertEquals(
      'anything1',
      $variables['#attached']['drupalSettings']['helfi_react_search']['elastic_proxy_url'],
      'Elastic proxy url should be set.'
    );
    $this->assertEquals(
      'anything2',
      $variables['#attached']['drupalSettings']['helfi_react_search']['sentry_dsn_react'],
      'Sentry dsn react should be set.'
    );
  }

  /**
   * Test hook_entity_bundle_field_info_alter() adds LinkedEvents constraint.
   */
  public function testEntityBundleFieldInfoAlterAddsConstraint(): void {
    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([])
    );

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('id')->willReturn('paragraph');

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->expects($this->once())
      ->method('addConstraint')
      ->with('LinkedEventsQueryString', []);

    $fields = [
      'field_event_list_free_text' => $fieldDefinition,
    ];

    $reactHooksClass->entityBundleFieldInfoAlter($fields, $entityType, 'event_list');
  }

  /**
   * Test hook_entity_bundle_field_info_alter() skips non-paragraph entities.
   */
  public function testEntityBundleFieldInfoAlterSkipsNonParagraph(): void {
    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([])
    );

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('id')->willReturn('node');

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->expects($this->never())->method('addConstraint');

    $fields = [
      'field_event_list_free_text' => $fieldDefinition,
    ];

    $reactHooksClass->entityBundleFieldInfoAlter($fields, $entityType, 'event_list');
  }

  /**
   * Test hook_entity_bundle_field_info_alter() skips non-event_list bundles.
   */
  public function testEntityBundleFieldInfoAlterSkipsNonEventListBundle(): void {
    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([])
    );

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('id')->willReturn('paragraph');

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->expects($this->never())->method('addConstraint');

    $fields = [
      'field_event_list_free_text' => $fieldDefinition,
    ];

    $reactHooksClass->entityBundleFieldInfoAlter($fields, $entityType, 'text');
  }

  /**
   * Test hook_entity_bundle_field_info_alter() skips when field is absent.
   */
  public function testEntityBundleFieldInfoAlterSkipsWhenFieldAbsent(): void {
    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([])
    );

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('id')->willReturn('paragraph');

    $fields = [];
    $reactHooksClass->entityBundleFieldInfoAlter($fields, $entityType, 'event_list');

    $this->assertSame([], $fields);
  }

  /**
   * Test hook_theme().
   */
  public function testTheme(): void {
    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([])
    );

    $this->assertIsArray($reactHooksClass->theme());
  }

  /**
   * Test hook_field_widget_single_element_paragraphs_form_alter().
   */
  public function testFieldWidgetSingleElementParagraphAlter(): void {
    $reactHooksClass = new ReactSearchHooks(
      $this->getConfigFactoryStub([])
    );

    $element = [
      '#paragraph_type' => 'event_list',
      '#delta' => 0,
    ];
    $formState = new FormState();
    $items = $this->createMock(EntityReferenceRevisionsFieldItemList::class);
    $items->method('getName')->willReturn('event_list');
    $items = FieldItemList::createInstance(
      $this->createMock(FieldDefinitionInterface::class),
      'event_list',
      NULL,
    );
    $context = ['items' => $items];

    $reactHooksClass->fieldWidgetSingleElementParagraphsFormAlter($element, $formState, $context);

    // These should have been added by the hook.
    $this->assertTrue(isset($element['subform']['field_event_list_category_event']));
    $this->assertTrue(isset($element['subform']['field_event_list_category_hobby']));
    $this->assertTrue(isset($element['subform']['field_event_location']));
    $this->assertTrue(isset($element['subform']['field_remote_events']));
    // Check one of the values.
    $this->assertTrue(
      $element['subform']['field_event_location']['#states']['disabled'][0][':input[name="event_list[0][subform][field_remote_events][value]"]']['checked']
    );
  }

}
