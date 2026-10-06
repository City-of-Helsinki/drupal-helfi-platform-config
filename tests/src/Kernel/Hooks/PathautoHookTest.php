<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\node\Entity\Node;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests hook_pathauto_pattern_alter().
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class PathautoHookTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'link',
    'menu_link_content',
    'menu_ui',
    'path',
    'path_alias',
    'token',
    'pathauto',
    'helfi_platform_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter', 'pathauto']);

    // The node form links to the node on save.
    $this->container->get('router.builder')->rebuild();

    // Make the main menu available on the node form.
    $type = $this->createContentType(['type' => 'page']);
    $type->setThirdPartySetting('menu_ui', 'available_menus', ['main']);
    $type->setThirdPartySetting('menu_ui', 'parent', 'main:');
    $type->save();

    PathautoPattern::create([
      'id' => 'page',
      'label' => 'Page',
      'type' => 'canonical_entities:node',
      'pattern' => '[node:title]',
      'weight' => 0,
    ])->save();

    // Menu settings are only shown to users who can administer menus.
    $this->setUpCurrentUser([], [], TRUE);
  }

  /**
   * Test path's for nodes outside of menu.
   */
  public function testNodePathWithoutMenuLinks(): void {
    $alias_repository = $this->container->get('path_alias.repository');

    // Assert that path should be node title for node without menu title.
    $node = $this->createPageByNodeForm('node-title', NULL, NULL);
    $alias = $alias_repository->lookupBySystemPath('/node/' . $node->id(), $node->language()->getId());
    $this->assertEquals('/node-title', $alias['alias'] ?? NULL);
  }

  /**
   * Test menu titles work properly on path.
   */
  public function testMenuTitleNodePath(): void {
    $alias_repository = $this->container->get('path_alias.repository');
    $entity_type_manager = $this->container->get('entity_type.manager');

    // Create first level link and assert.
    $node = $this->createPageByNodeForm('node-title', 'menu-title', NULL);
    $alias = $alias_repository->lookupBySystemPath('/node/' . $node->id(), $node->language()->getId());
    $this->assertEquals('/menu-title', $alias['alias'] ?? NULL);

    $links = $entity_type_manager->getStorage('menu_link_content')
      ->loadByProperties(['link.uri' => 'entity:node/' . $node->id()]);
    $parent_link = reset($links);
    $this->assertNotNull($parent_link);

    // Create sub page and assert.
    $node2 = $this->createPageByNodeForm('node-title2', 'sub-menu-title', $parent_link->getPluginId());
    $alias = $alias_repository->lookupBySystemPath('/node/' . $node2->id(), $node2->language()->getId());
    $this->assertEquals('/menu-title/sub-menu-title', $alias['alias'] ?? NULL);
  }

  /**
   * Create a node by submitting node-edit form.
   *
   * @param string $nodeTitle
   *   The node title.
   * @param string|null $menuTitle
   *   Title for menu item.
   * @param string|null $parentPluginId
   *   Menu item parent if nested menu.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The node.
   */
  private function createPageByNodeForm(string $nodeTitle, ?string $menuTitle, ?string $parentPluginId): EntityInterface {
    $form_object = $this->container->get('entity_type.manager')
      ->getFormObject('node', 'default')
      ->setEntity(Node::create(['type' => 'page']));

    $formValues = [];
    $formValues['title'] = [['value' => $nodeTitle]];
    $formValues['op'] = 'Save';

    if ($menuTitle) {
      $formValues['menu'] = [
        'enabled' => 1,
        'title' => $menuTitle,
        'description' => '',

        'menu_parent' => 'main:' . $parentPluginId,
        'weight' => 0,
      ];
    }

    $form_state = (new FormState())->setValues($formValues);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    return $form_object->getEntity();
  }

}
