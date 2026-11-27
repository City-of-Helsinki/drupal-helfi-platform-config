<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_ai\Hook\ToneCheckHooks;
use Drupal\helfi_ai\ToneCheck\ToneCheckPrecheck;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the tone check pre-check.
 */
#[Group('helfi_ai')]
#[CoversClass(ToneCheckPrecheck::class)]
#[CoversClass(ToneCheckHooks::class)]
#[RunTestsInSeparateProcesses]
class ToneCheckPrecheckTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'diff',
    'helfi_api_base',
    'config_rewrite',
    'node',
    'language',
    'helfi_ai',
    'helfi_entity_task',
    'system',
  ];

  /**
   * The pre-check service.
   */
  private ToneCheckPrecheck $precheck;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('helfi_entity_task', ['helfi_entity_task']);
    $this->installConfig(['system', 'helfi_ai']);
    $this->config('helfi_ai.settings')->set('enable_tone_check', TRUE)->save();

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_lead_in',
      'entity_type' => 'node',
      'type' => 'string_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_lead_in',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();

    $this->precheck = $this->container->get(ToneCheckPrecheck::class);
  }

  /**
   * Creates a page with the given number of words in its lead-in.
   *
   * @param int $words
   *   The number of words.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved page.
   */
  private function createPage(int $words): NodeInterface {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Page',
      'field_lead_in' => $this->words('word', $words),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Builds a text of numbered words.
   *
   * @param string $prefix
   *   The word prefix.
   * @param int $count
   *   The number of words.
   *
   * @return string
   *   The text.
   */
  private function words(string $prefix, int $count): string {
    return implode(' ', array_map(static fn (int $i): string => $prefix . $i, range(1, $count)));
  }

  /**
   * Saves the node as if the editor saved it through the edit form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $leadIn
   *   The new lead-in text.
   */
  private function editorSave(NodeInterface $node, string $leadIn): void {
    $node->set('field_lead_in', $leadIn);
    $this->precheck->registerEditorSave($node);
    $node->save();
  }

  /**
   * The node form registers its save, which flags a new page.
   */
  public function testFormSaveFlagsNewPage(): void {
    $node = Node::create(['type' => 'page', 'title' => 'New page']);
    $formObject = $this->container->get('entity_type.manager')->getFormObject('node', 'default')->setEntity($node);
    $formState = (new FormState())->setFormObject($formObject);
    $form = ['actions' => ['submit' => ['#submit' => ['::submitForm', '::save']]]];

    ToneCheckHooks::create($this->container)->nodeFormAlter($form, $formState);
    $this->assertSame(['::submitForm', [ToneCheckHooks::class, 'registerEditorSave'], '::save'], $form['actions']['submit']['#submit']);

    ToneCheckHooks::registerEditorSave($form, $formState);
    $node->save();
    $this->assertTrue($this->precheck->isPending($node));
    $this->assertSame(['helfi_entity_task:node:' . $node->id()], $this->precheck->getCacheTags($node));
  }

  /**
   * Editor saves are flagged by the pre-check rules.
   */
  public function testRules(): void {
    $page = $this->createPage(100);
    $this->assertFalse($this->precheck->isPending($page));

    $this->editorSave($page, $this->words('word', 100) . ' ' . $this->words('new', 5));
    $this->assertFalse($this->precheck->isPending($page));

    $this->editorSave($page, $this->words('word', 100) . ' ' . $this->words('new', 13));
    $this->assertTrue($this->precheck->isPending($page));

    $this->precheck->complete($page);
    $this->editorSave($page, $this->words('rewrite', 120));
    $this->assertFalse($this->precheck->isPending($page));

    $old = $this->createPage(10);
    $this->container->get('database')->update('node_field_data')
      ->fields(['changed' => strtotime('-2 years')])
      ->condition('nid', $old->id())
      ->execute();
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache([$old->id()]);
    $old = Node::load($old->id());
    $this->assertInstanceOf(NodeInterface::class, $old);
    $this->editorSave($old, $this->words('word', 10));
    $this->assertTrue($this->precheck->isPending($old));

    $this->config('helfi_ai.settings')->set('enable_tone_check', FALSE)->save();
    $disabled = Node::create(['type' => 'page', 'title' => 'Disabled']);
    $this->precheck->registerEditorSave($disabled);
    $disabled->save();
    $this->config('helfi_ai.settings')->set('enable_tone_check', TRUE)->save();
    $this->assertFalse($this->precheck->isPending($disabled));

    $this->assertSame(0.0, $this->precheck->getChangeRatio('', ''));
    $this->assertSame('a  b', $this->precheck->extractText(Node::create([
      'type' => 'page',
      'title' => 'Text',
      'field_lead_in' => '<p>a</p><p>b</p>',
    ])));
  }

}
