<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\TwigExtension;

use Drupal\KernelTests\KernelTestBase;
use Drupal\helfi_platform_config\TwigExtension\DrupalEntityRevision;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Kernel tests for the DrupalEntityRevision Twig extension.
 *
 * This test suite verifies that the DrupalEntityRevision Twig extension
 * correctly loads entity revisions and generates appropriate render arrays.
 * The tests cover the following scenarios:
 *
 * - Registration of the Twig function.
 * - Loading entity revisions by UUID.
 * - Loading entity revisions by revision ID.
 * - Handling cases where no entity revision is found.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\TwigExtension\DrupalEntityRevision
 * @group helfi_platform_config
 */
class DrupalEntityRevisionTest extends KernelTestBase {

  /**
   * Modules to enable for this test.
   *
   * Note that we don't enable the helfi_platform_config module directly
   * to avoid dependency issues. Instead, we manually instantiate the
   * DrupalEntityRevision class and mock the necessary services.
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'filter',
  ];

  /**
   * The DrupalEntityRevision Twig extension instance being tested.
   *
   * This is the main class under test, which provides the
   * 'drupal_entity_revision' Twig function for loading and rendering entity
   * revisions.
   *
   * @var \Drupal\helfi_platform_config\TwigExtension\DrupalEntityRevision
   */
  protected $twigExtension;

  /**
   * The test node entity with multiple revisions.
   *
   * This node is created in the setUp method and has two revisions:
   * - Initial revision with 'Original title'
   * - Second revision with 'Updated title'
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * Sets up the test environment.
   *
   * This method:
   * 1. Installs necessary entity schemas and configurations
   * 2. Creates a test node type
   * 3. Creates a test node with multiple revisions
   * 4. Sets up a mock entity view builder service
   * 5. Instantiates the DrupalEntityRevision class.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['field', 'system', 'node', 'filter']);
    $this->installSchema('node', ['node_access']);

    // Create a node type for testing.
    $nodeType = NodeType::create([
      'type' => 'test_page',
      'name' => 'Test Page',
    ]);
    $nodeType->save();

    // Create a test node entity.
    $this->node = Node::create([
      'type' => 'test_page',
      'title' => 'Original title',
    ]);
    $this->node->save();

    // Create a new revision of the node.
    $this->node->setNewRevision(TRUE);
    $this->node->set('title', 'Updated title');
    $this->node->save();

    // Create a mock entity view builder service that returns a simple render
    // array. This replaces the actual twig_tweak.entity_view_builder service.
    // Our mock simply returns a basic render array with the entity.
    $entityViewBuilder = new class() {

      /**
       * Builds a render array for the given entity.
       *
       * @param object $entity
       *   The entity to build a render array for.
       *
       * @return array
       *   The render array.
       */
      public function build($entity) {
        return [
          '#theme' => 'node',
          '#node' => $entity,
        ];
      }

    };

    // Set up the Drupal container with our mocked services.
    // This is necessary because DrupalEntityRevision uses static service calls
    // (Drupal::service()) which require a properly configured container.
    $container = \Drupal::getContainer();
    $container->set('twig_tweak.entity_view_builder', $entityViewBuilder);
    \Drupal::setContainer($container);

    // Instantiate the Twig extension.
    $this->twigExtension = new DrupalEntityRevision();
  }

  /**
   * Tests that the Twig function is correctly registered.
   *
   * This test verifies that the DrupalEntityRevision class properly registers
   * the 'drupal_entity_revision' Twig function and associates it with the
   * entityRevision method.
   *
   * @covers ::getFunctions
   */
  public function testGetFunctions(): void {
    $functions = $this->twigExtension->getFunctions();

    // Assert that the function is registered.
    $this->assertCount(1, $functions);
    $this->assertEquals('drupal_entity_revision', $functions[0]->getName());
  }

  /**
   * Tests loading an entity revision by its revision ID.
   *
   * This test verifies that the entityRevision method correctly:
   * 1. Loads a node entity using its revision ID
   * 2. Passes the entity to the entity view builder
   * 3. Returns the appropriate render array.
   *
   * The test confirms that when Uuid::isValid() returns false (for a numeric
   * ID)
   * the method falls back to loading the entity by revision ID.
   *
   * @covers ::entityRevision
   */
  public function testEntityRevisionById(): void {
    $revisionId = $this->node->getRevisionId();

    // Call the method under test.
    $result = DrupalEntityRevision::entityRevision('node', (string) $revisionId);

    // Assert that the result is a render array.
    $this->assertIsArray($result);
    $this->assertArrayHasKey('#theme', $result);
    $this->assertEquals('node', $result['#theme']);

    // Verify we got the correct entity.
    $this->assertEquals($this->node->id(), $result['#node']->id());
    $this->assertEquals($revisionId, $result['#node']->getRevisionId());
    $this->assertEquals('Updated title', $result['#node']->getTitle());
  }

  /**
   * Tests handling when no entity revision is found.
   *
   * This test verifies that the entityRevision method correctly handles
   * the case
   * when no entity can be found for the given selector. Specifically, it
   * confirms that:
   * 1. The method attempts to load by UUID but finds nothing
   * 2. The method falls back to loading by revision ID but finds nothing
   * 3. The method returns an empty array when no entity is found.
   *
   * This test ensures proper error handling in the entityRevision method.
   *
   * @covers ::entityRevision
   */
  public function testEntityRevisionNotFound(): void {
    // Call the method with an invalid ID.
    $result = DrupalEntityRevision::entityRevision('node', '999999');

    // Assert that the result is an empty array.
    $this->assertEquals([], $result);
  }

  /**
   * Tests loading an entity revision by its UUID.
   *
   * This test verifies that the entityRevision method correctly:
   * 1. Determines that the selector is a valid UUID
   * 2. Loads the entity using the UUID via loadByProperties()
   * 3. Passes the entity to the entity view builder
   * 4. Returns the appropriate render array.
   *
   * The test confirms that when Uuid::isValid() returns true the method
   * loads the entity by UUID rather than revision ID.
   *
   * @covers ::entityRevision
   */
  public function testEntityRevisionByUuid(): void {
    $uuid = $this->node->uuid();

    // Call the method under test.
    $result = DrupalEntityRevision::entityRevision('node', $uuid);

    // Assert that the result is a render array.
    $this->assertIsArray($result);
    $this->assertArrayHasKey('#theme', $result);
    $this->assertEquals('node', $result['#theme']);

    // Verify we got the correct entity.
    $this->assertEquals($this->node->id(), $result['#node']->id());
    $this->assertEquals('Updated title', $result['#node']->getTitle());
  }

}
