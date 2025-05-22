<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Entity\TranslatableRevisionableInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for EntityVersionMatcher service.
 *
 * - Identification of canonical entity view context.
 * - Detection and handling of revision entity view context.
 * - Recognition of preview entity view context.
 * - Behavior when no entity context is present.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\EntityVersionMatcher
 * @group helfi_platform_config
 */
class EntityVersionMatcherTest extends UnitTestCase {

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private EntityTypeManagerInterface|MockObject $entityTypeManager;

  /**
   * The route match mock.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private RouteMatchInterface|MockObject $routeMatch;

  /**
   * The language manager mock.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private LanguageManagerInterface|MockObject $languageManager;

  /**
   * The entity version matcher instance.
   *
   * @var \Drupal\helfi_platform_config\EntityVersionMatcher
   */
  private EntityVersionMatcher $entityVersionMatcher;

  /**
   * Set up the test environment.
   *
   * Initializes mock objects for dependencies and creates an instance of
   * EntityVersionMatcher for testing.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);

    $this->entityVersionMatcher = new EntityVersionMatcher(
      $this->entityTypeManager,
      $this->routeMatch,
      $this->languageManager
    );
  }

  /**
   * Set up entity type definitions mock.
   *
   * Configures the entity type manager to return a predefined set of entity
   * type definitions, simulating the available entity types in the system.
   *
   * @param array $definitions
   *   Array of entity type definitions to return, defaults to 'node' and 'user'.
   */
  private function setupEntityTypeDefinitions(array $definitions = ['node' => [], 'user' => []]): void {
    $this->entityTypeManager->method('getDefinitions')->willReturn($definitions);
  }

  /**
   * Set up route parameters mock.
   *
   * Configures the route match object to return specific parameters and raw
   * values, simulating different routing contexts for entity detection.
   *
   * @param array $parameters
   *   Associative array of route parameters to return.
   * @param mixed $rawParameter
   *   Raw parameter value to return for getRawParameter(), or NULL if none.
   * @param string $routeName
   *   The route name to return, defaults to a generic route.
   */
  private function setupRouteParameters(array $parameters = [], $rawParameter = NULL, string $routeName = 'some.route'): void {
    $this->routeMatch->method('getParameters')->willReturn(new class($parameters) {
      /**
       * The parameters array.
       *
       * @var array
       */
      private $params;

      /**
       * Constructor.
       *
       * @param array $params
       *   The parameters to set.
       */
      public function __construct($params) {
        $this->params = $params;
      }

      /**
       * Returns all parameters.
       *
       * @return array
       *   The parameters array.
       */
      public function all() {
        return $this->params;
      }

    });
    $this->routeMatch->method('getRawParameter')->willReturn($rawParameter);
    $this->routeMatch->method('getRouteName')->willReturn($routeName);
  }

  /**
   * Set up entity type manager storage mock.
   *
   * Configures the entity type manager to return a specific storage mock
   * for a given entity type, along with revisionable storage behavior if
   * provided.
   *
   * @param string $entityType
   *   The entity type to mock storage for.
   * @param mixed $storage
   *   The storage mock to return.
   * @param array $revisionable
   *   Array of revisionable storage behaviors if applicable.
   */
  private function setupStorageMock($entityType, $storage, array $revisionable = []): void {
    $this->entityTypeManager->method('getStorage')->with($entityType)->willReturn($storage);
  }

  /**
   * Tests getType() for a canonical entity view context.
   *
   * Verifies that the method identifies a canonical view and returns the
   * correct version constant and entity.
   *
   * @covers \Drupal\helfi_platform_config\EntityVersionMatcher::getType
   */
  public function testGetTypeCanonical(): void {
    // Set up entity type definitions to include node and user.
    $this->setupEntityTypeDefinitions();

    // Set up route parameters to include a node reference and a canonical route.
    $this->setupRouteParameters(['node' => 1], NULL, 'entity.node.canonical');

    // Mock the entity object that should be returned for the canonical view.
    $entity = $this->createMock(ContentEntityInterface::class);
    $this->routeMatch->method('getParameter')->willReturn($entity);

    // Set up the current language for context, though not directly used in
    // canonical logic.
    $language = $this->createMock(LanguageInterface::class);
    $this->languageManager->method('getCurrentLanguage')->willReturn($language);

    // Call the getType method to test its behavior for a canonical entity view
    // context.
    // This method should return the correct entity version constant and entity
    // object.
    $result = $this->entityVersionMatcher->getType();

    // Assert that the method returns the correct entity version constant and
    // entity object.
    $this->assertEquals(EntityVersionMatcher::ENTITY_VERSION_CANONICAL, $result['entity_version']);
    $this->assertEquals($entity, $result['entity']);
  }

  /**
   * Tests getType() for a revision entity view context.
   *
   * Verifies that the method identifies a revision view, loads it, handles
   * translation, and returns the correct data.
   *
   * @covers \Drupal\helfi_platform_config\EntityVersionMatcher::getType
   */
  public function testGetTypeRevision(): void {
    // Set up entity type definitions to include node and user.
    $this->setupEntityTypeDefinitions();

    // Set up route parameters to include a node reference and a revision route.
    $this->setupRouteParameters(['node' => 1], NULL, 'entity.node.revision');

    // Mock the storage to return a revisionable entity storage for nodes.
    $storage = $this->createMock(RevisionableStorageInterface::class);
    $this->setupStorageMock('node', $storage);

    // Mock a revision entity that can be loaded and translated.
    $revision = $this->createMock(TranslatableRevisionableInterface::class);
    $storage->method('loadRevision')->willReturn($revision);

    // Set up the current language to ensure translation is fetched correctly.
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');
    $this->languageManager->method('getCurrentLanguage')->willReturn($language);

    // Mock the translated entity that should be returned for the current
    // language.
    $entity = $this->createMock(ContentEntityInterface::class);
    $revision->method('getTranslation')->willReturn($entity);

    // Provide a revision ID as a parameter to simulate loading a specific
    // revision.
    $this->routeMatch->method('getParameter')->willReturn(123);

    // Call the getType method to test its behavior for a revision entity view
    // context.
    $result = $this->entityVersionMatcher->getType();

    // Assert that the method returns the correct entity version constant and
    // entity object.
    $this->assertEquals(EntityVersionMatcher::ENTITY_VERSION_REVISION, $result['entity_version']);
    $this->assertEquals($entity, $result['entity']);
  }

  /**
   * Tests getType() for a preview entity view context.
   *
   * Verifies that the method identifies a preview view and returns the correct
   * version constant and entity.
   *
   * @covers \Drupal\helfi_platform_config\EntityVersionMatcher::getType
   */
  public function testGetTypePreview(): void {
    // Set up entity type definitions to include node and user.
    $this->setupEntityTypeDefinitions();

    // Set up route parameters to include a node reference and a preview route.
    $this->setupRouteParameters(['node' => 1], NULL, 'entity.node.preview');

    // Mock the entity object that should be returned for the preview view.
    $entity = $this->createMock(ContentEntityInterface::class);
    $this->routeMatch->method('getParameter')->willReturn($entity);

    // Set up the current language for context, though not directly used in
    // preview logic.
    $language = $this->createMock(LanguageInterface::class);
    $this->languageManager->method('getCurrentLanguage')->willReturn($language);

    // Call the getType method to test its behavior for a preview entity view
    // context.
    // This method should return the correct entity version constant and entity
    // object.
    $result = $this->entityVersionMatcher->getType();

    // Assert that the method returns the correct entity version constant and
    // entity object.
    $this->assertEquals(EntityVersionMatcher::ENTITY_VERSION_PREVIEW, $result['entity_version']);
    $this->assertEquals($entity, $result['entity']);
  }

  /**
   * Tests getType() when no entity context is present.
   *
   * Verifies that the method returns an empty array when no entity or version
   * context is detected.
   *
   * @covers \Drupal\helfi_platform_config\EntityVersionMatcher::getType
   */
  public function testGetTypeNoEntity(): void {
    $this->setupEntityTypeDefinitions();
    $this->setupRouteParameters([], NULL, 'some.other.route');

    $result = $this->entityVersionMatcher->getType();
    $this->assertEquals([
      'entity_version' => EntityVersionMatcher::ENTITY_VERSION_CANONICAL,
      'entity' => FALSE,
    ], $result);
  }

}
