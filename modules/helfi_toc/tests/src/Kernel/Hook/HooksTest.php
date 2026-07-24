<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_toc\Kernel\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\helfi_toc\Hook\EntityHooks;
use Drupal\helfi_toc\Hook\FormHooks;
use Drupal\helfi_toc\Hook\ThemeHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests helfi_toc hooks.
 *
 * @group helfi_toc
 */
class HooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'helfi_toc',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
  }

  /**
   * Test that the TOC fields are available.
   */
  public function testEntityBaseFieldInfo(): void {
    $hooks = new EntityHooks();

    $fields = $hooks->entityBaseFieldInfo($this->entityType('node'));
    $this->assertArrayHasKey('toc_enabled', $fields);
    $this->assertArrayHasKey('toc_title', $fields);
    $this->assertInstanceOf(BaseFieldDefinition::class, $fields['toc_enabled']);
    $this->assertInstanceOf(BaseFieldDefinition::class, $fields['toc_title']);

    foreach (['tpr_unit', 'tpr_service'] as $tprEntity) {
      $tprFields = $hooks->entityBaseFieldInfo($this->entityType($tprEntity));
      $this->assertTrue($tprFields['toc_enabled']->isDisplayConfigurable('form'));
    }

    $this->assertSame([], $hooks->entityBaseFieldInfo($this->entityType('block')));
  }

  /**
   * Test that whitelisted forms show the table of contents.
   */
  #[DataProvider('whitelistedFormProvider')]
  public function testFormAlterWhitelistedForm(string $method, string $form_id): void {
    $hooks = new FormHooks();

    /** @var array<string, mixed> $form */
    $form = [
      '#form_id' => $form_id,
      'toc_enabled' => [],
      'toc_title' => [],
    ];
    $hooks->$method($form);

    $this->assertTrue($form['toc_enabled']['#access']);
    $this->assertFalse($form['toc_title']['#access']);
    $this->assertArrayHasKey('#states', $form['toc_title']);
  }

  /**
   * Data provider mapping for TOC alter hooks.
   *
   * @return array<string, array{string, string}>
   *   The hook method name and matching whitelisted form id.
   */
  public static function whitelistedFormProvider(): array {
    return [
      'tpr_service' => ['tprServiceFormAlter', 'tpr_service_form'],
      'tpr_unit' => ['tprUnitFormAlter', 'tpr_unit_form'],
      'node' => ['nodeFormAlter', 'node_page_form'],
    ];
  }

  /**
   * Test that non whitelisted forms deny access to TOC fields.
   */
  public function testFormAlterOtherForm(): void {
    $hooks = new FormHooks();

    /** @var array<string, mixed> $form */
    $form = [
      '#form_id' => 'some_other_form',
      'toc_enabled' => [],
      'toc_title' => [],
    ];
    $hooks->nodeFormAlter($form);

    $this->assertFalse($form['toc_enabled']['#access']);
    $this->assertFalse($form['toc_title']['#access']);
    $this->assertArrayNotHasKey('#states', $form['toc_title']);
  }

  /**
   * Test that the theme gets a correct template.
   */
  public function testTheme(): void {
    $hooks = new ThemeHooks($this->container->get('extension.list.module'));

    $theme = $hooks->theme();
    $this->assertArrayHasKey('field__toc_enabled', $theme);
    $this->assertSame('field', $theme['field__toc_enabled']['base hook']);
    $this->assertStringContainsString('helfi_toc/templates', $theme['field__toc_enabled']['path']);
  }

  /**
   * Test that the suggestion is added only for the toc_enabled field.
   */
  public function testThemeSuggestionsFieldAlter(): void {
    $hooks = new ThemeHooks($this->container->get('extension.list.module'));

    $suggestions = [];
    $hooks->themeSuggestionsFieldAlter($suggestions, ['element' => ['#field_name' => 'toc_enabled']]);
    $this->assertContains('field__toc_enabled', $suggestions);

    $suggestions = [];
    $hooks->themeSuggestionsFieldAlter($suggestions, ['element' => ['#field_name' => 'body']]);
    $this->assertNotContains('field__toc_enabled', $suggestions);
  }

  /**
   * Test that an enabled entity sets the variables and attaches the library.
   */
  public function testPreprocessFieldTocEnabled(): void {
    $hooks = new ThemeHooks($this->container->get('extension.list.module'));

    $node = Node::create([
      'type' => 'page',
      'title' => 'Test',
      'toc_enabled' => TRUE,
    ]);
    $node->save();

    $variables = ['element' => ['#object' => $node]];
    $hooks->preprocessFieldTocEnabled($variables);

    $this->assertTrue((bool) $variables['toc_enabled']);
    $this->assertContains('helfi_toc/table_of_contents', $variables['#attached']['library']);
  }

  /**
   * Test that a non-content entity leaves the variables untouched.
   */
  public function testPreprocessFieldTocEnabledReturnsEarly(): void {
    $hooks = new ThemeHooks($this->container->get('extension.list.module'));

    $variables = ['element' => ['#object' => NULL]];
    $hooks->preprocessFieldTocEnabled($variables);

    $this->assertArrayNotHasKey('toc_enabled', $variables);
  }

  /**
   * Builds an entity type stub with the given id.
   *
   * @param string $id
   *   The entity type id.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity type stub.
   */
  private function entityType(string $id): EntityTypeInterface {
    $entity_type = $this->prophesize(EntityTypeInterface::class);
    $entity_type->id()->willReturn($id);
    return $entity_type->reveal();
  }

}
