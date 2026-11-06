<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_etusivu_entities\PagePathMatcher;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\path_alias\Entity\PathAlias;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests page path pattern matching.
 */
#[Group('helfi_platform_config')]
final class PagePathMatcherTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installConfig(['system']);
    $this->config('system.site')->set('page.front', '/frontpage')->save();
    // Alias lookup ignores path roots that are not registered as routes.
    $this->container->get('state')->set('router.path_roots', [
      'node',
      'news',
      'frontpage',
      'feedback',
      'other',
    ]);

    FieldStorageConfig::create([
      'field_name' => 'field_page_paths',
      'entity_type' => 'entity_test',
      'type' => 'string_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_page_paths',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Page paths',
    ])->save();

    PathAlias::create([
      'path' => '/node/5',
      'alias' => '/Feedback',
    ])->save();
  }

  /**
   * Tests alias, internal path, wildcard, and front page matching.
   *
   * @param string $path
   *   The request path.
   * @param bool $expected
   *   Whether the path should match.
   */
  #[DataProvider('pathProvider')]
  public function testPathMatching(string $path, bool $expected): void {
    $entity = $this->entity("/FEEDBACK\n/News/*\n<front>\n/node/9");

    $this->assertSame($expected, $this->pathMatches($entity, $path));
  }

  /**
   * Data provider for testPathMatching().
   *
   * @return array<string, array{string, bool}>
   *   Request paths and the expected match result.
   */
  public static function pathProvider(): array {
    return [
      'alias of the internal path' => ['/node/5', TRUE],
      'alias with its stored case' => ['/Feedback', TRUE],
      'wildcard child' => ['/news/item', TRUE],
      'wildcard child with trailing slash' => ['/news/item/', TRUE],
      'wildcard child with different case' => ['/NEWS/Item', TRUE],
      'wildcard parent' => ['/news', FALSE],
      'front page' => ['/frontpage', TRUE],
      'internal path' => ['/node/9', TRUE],
      'unrelated path' => ['/other', FALSE],
      'root path' => ['/', FALSE],
    ];
  }

  /**
   * Tests that client error pages never match.
   *
   * @param string|null $route
   *   The route name, when the request is a system error page.
   * @param int|null $status
   *   The exception status code, when the error uses a custom page.
   * @param string $method
   *   The request method. POST stores the status code in the request body.
   */
  #[DataProvider('errorPageProvider')]
  public function testErrorPagesDoNotMatch(?string $route, ?int $status, string $method): void {
    $entity = $this->entity('/news/*');

    $this->assertFalse($this->pathMatches($entity, '/news/item', $method, $route, $status));
  }

  /**
   * Data provider for testErrorPagesDoNotMatch().
   *
   * @return array<string, array{string|null, int|null, string}>
   *   Error page requests that would otherwise match /news/*.
   */
  public static function errorPageProvider(): array {
    return [
      'system 401' => ['system.401', NULL, 'GET'],
      'system 403' => ['system.403', NULL, 'GET'],
      'system 404' => ['system.404', NULL, 'GET'],
      'system 4xx' => ['system.4xx', NULL, 'GET'],
      'custom page 400' => [NULL, 400, 'GET'],
      'custom page 403' => [NULL, 403, 'GET'],
      'custom page 499' => [NULL, 499, 'GET'],
      'custom page 404 on POST' => [NULL, 404, 'POST'],
    ];
  }

  /**
   * Tests that an empty pattern field does not match.
   */
  public function testEmptyFieldDoesNotMatch(): void {
    $this->assertFalse($this->pathMatches($this->entity(''), '/news/item'));
    $this->assertFalse($this->pathMatches($this->entity(NULL), '/news/item'));
  }

  /**
   * Tests that a missing field does not match.
   */
  public function testMissingFieldDoesNotMatch(): void {
    $entity = EntityTest::create(['name' => 'Visibility']);
    $entity->save();

    $requestStack = $this->createMock(RequestStack::class);
    $requestStack->method('getCurrentRequest')->willReturn(NULL);

    $this->assertFalse($this->matcher($this->createMock(CurrentPathStack::class), $requestStack)->matches($entity, 'field_missing'));
  }

  /**
   * Tests that matching is skipped when there is no request.
   */
  public function testMissingRequestDoesNotMatch(): void {
    $entity = $this->entity('/news/*');
    $currentPath = $this->createMock(CurrentPathStack::class);
    $currentPath->expects($this->never())->method('getPath');
    $requestStack = $this->createMock(RequestStack::class);
    $requestStack->method('getCurrentRequest')->willReturn(NULL);

    $this->assertFalse($this->matcher($currentPath, $requestStack)->matches($entity, 'field_page_paths'));
  }

  /**
   * Creates an entity with page path patterns.
   *
   * @param string|null $patterns
   *   The pattern field value.
   *
   * @return \Drupal\entity_test\Entity\EntityTest
   *   The saved entity.
   */
  private function entity(?string $patterns): EntityTest {
    $entity = EntityTest::create([
      'name' => 'Visibility',
      'field_page_paths' => $patterns,
    ]);
    $entity->save();
    return $entity;
  }

  /**
   * Matches the entity against a request path.
   *
   * @param \Drupal\entity_test\Entity\EntityTest $entity
   *   The entity whose patterns are checked.
   * @param string $path
   *   The request path.
   * @param string $method
   *   The request method.
   * @param string|null $route
   *   The route name.
   * @param int|null $status
   *   The exception status code.
   *
   * @return bool
   *   TRUE when the patterns match the request.
   */
  private function pathMatches(EntityTest $entity, string $path, string $method = 'GET', ?string $route = NULL, ?int $status = NULL): bool {
    $parameters = $status === NULL ? [] : ['_exception_statuscode' => $status];
    $request = Request::create($path, $method, $parameters);
    if ($route !== NULL) {
      $request->attributes->set('_route', $route);
    }

    $requestStack = $this->createMock(RequestStack::class);
    $requestStack->method('getCurrentRequest')->willReturn($request);
    $currentPath = $this->createMock(CurrentPathStack::class);
    $currentPath->method('getPath')->willReturn($path);

    return $this->matcher($currentPath, $requestStack)->matches($entity, 'field_page_paths');
  }

  /**
   * Builds the matcher with the real path and alias services.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   *
   * @return \Drupal\helfi_etusivu_entities\PagePathMatcher
   *   The matcher.
   */
  private function matcher(CurrentPathStack $currentPath, RequestStack $requestStack): PagePathMatcher {
    $pathMatcher = $this->container->get('path.matcher');
    $aliasManager = $this->container->get('path_alias.manager');
    if (!$pathMatcher instanceof PathMatcherInterface || !$aliasManager instanceof AliasManagerInterface) {
      throw new \RuntimeException('The path services are missing.');
    }

    return new PagePathMatcher($pathMatcher, $aliasManager, $currentPath, $requestStack);
  }

}
