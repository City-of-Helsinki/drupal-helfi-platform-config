<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Hooks;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\helfi_platform_config\Hook\ChangedAtFieldHooks;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;

/**
 * Tests the 'changed_at' field hooks.
 */
final class ChangedAtFieldHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'helfi_platform_config_update_test',
    'node',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'node']);

    // 'page' opts in via helfi_platform_config_update_test's
    // hook_helfi_changed_at_field_bundles(); 'article' does not.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Tests that the field is added to a bundle that opted in.
   */
  public function testFieldAddedToConfiguredBundle(): void {
    $fields = $this->container->get('entity_field.manager')->getFieldDefinitions('node', 'page');

    $this->assertArrayHasKey('changed_at', $fields);

    $field = $fields['changed_at'];
    $this->assertInstanceOf(FieldStorageDefinitionInterface::class, $field);
    $this->assertSame('timestamp', $field->getType());
    $this->assertTrue($field->isRevisionable());
    $this->assertTrue($field->isTranslatable());
    $this->assertTrue($field->isDisplayConfigurable('view'));
    $this->assertFalse($field->isDisplayConfigurable('form'));
  }

  /**
   * Tests that the field is not added to a bundle that did not opt in.
   */
  public function testFieldNotAddedToUnconfiguredBundle(): void {
    $fields = $this->container->get('entity_field.manager')->getFieldDefinitions('node', 'article');

    $this->assertArrayNotHasKey('changed_at', $fields);
  }

  /**
   * Tests that the field storage definition is available for the entity type.
   */
  public function testFieldStorageDefinitionIsAvailable(): void {
    $storageDefinitions = $this->container->get('entity_field.manager')->getFieldStorageDefinitions('node');

    $this->assertArrayHasKey('changed_at', $storageDefinitions);
    $this->assertSame('timestamp', $storageDefinitions['changed_at']->getType());
  }

  /**
   * Builds the default node form for the given node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node.
   *
   * @return array<string, mixed>
   *   The built form.
   */
  private function buildNodeForm(Node $node): array {
    $formObject = $this->container->get('entity_type.manager')->getFormObject('node', 'default');
    $formObject->setEntity($node);
    $formState = new FormState();
    $formState->setFormObject($formObject);

    return $this->container->get('form_builder')->buildForm($formObject, $formState);
  }

  /**
   * Tests that the submit handler is attached for a bundle that opted in.
   */
  public function testSubmitHandlerAttachedForConfiguredBundle(): void {
    $node = Node::create(['type' => 'page', 'title' => 'test']);
    $form = $this->buildNodeForm($node);

    $this->assertContains(
      [ChangedAtFieldHooks::class, 'updateChangedAt'],
      $form['actions']['submit']['#submit'],
    );
  }

  /**
   * Tests the submit handler for a bundle that did not opt in.
   */
  public function testSubmitHandlerNotAttachedForUnconfiguredBundle(): void {
    $node = Node::create(['type' => 'article', 'title' => 'test']);
    $form = $this->buildNodeForm($node);

    $this->assertNotContains(
      [ChangedAtFieldHooks::class, 'updateChangedAt'],
      $form['actions']['submit']['#submit'],
    );
  }

  /**
   * Tests that 'changed_at' is only updated by the form submit handler.
   */
  public function testChangedAtOnlyUpdatedViaFormSubmit(): void {
    $node = Node::create(['type' => 'page', 'title' => 'test']);
    $node->save();

    // A programmatic/API save must not set 'changed_at'.
    $this->assertTrue($node->get('changed_at')->isEmpty());

    $formObject = $this->container->get('entity_type.manager')->getFormObject('node', 'default');
    $formObject->setEntity($node);
    $formState = new FormState();
    $formState->setFormObject($formObject);

    ChangedAtFieldHooks::updateChangedAt([], $formState);

    $this->assertSame(
      $this->container->get('datetime.time')->getRequestTime(),
      (int) $node->get('changed_at')->value,
    );
  }

}
