<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * Shared setup for announcement and survey lazy builder tests.
 */
abstract class LazyBuilderTestBase extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'diff',
    'helfi_api_base',
    'helfi_platform_config',
    'node',
    'path',
    'path_alias',
    'field_group',
    'hdbt_admin_tools',
    'publication_date',
    'link',
    'language',
    'allowed_formats',
    'select2',
    'content_translation',
    'text',
    'options',
    'menu_ui',
    'scheduler',
    'config_rewrite',
    'external_entities',
    'helfi_etusivu_entities',
    'helfi_node_announcement',
    'helfi_node_survey',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['node']);

    // Content-page references depend on these bundles. The full page and
    // landing page modules pull in config these tests do not need.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create([
      'type' => 'landing_page',
      'name' => 'Landing page',
    ])->save();

    $this->installConfig([
      'helfi_platform_config',
      'helfi_etusivu_entities',
      'helfi_node_announcement',
      'helfi_node_survey',
    ]);

    $this->drupalSetUpCurrentUser([], [
      'access content',
      'bypass node access',
    ]);
  }

  /**
   * The lazy builder class under test.
   *
   * @return class-string<\Drupal\helfi_etusivu_entities\LazyBuilderBase>
   *   The builder class.
   */
  abstract protected function builderClass(): string;

  /**
   * Tests that the builder exposes the shared lazy callback.
   */
  public function testTrustedCallbacks(): void {
    $class = $this->builderClass();
    $this->assertSame(['lazyBuild'], $class::trustedCallbacks());
  }

  /**
   * Creates a page node.
   *
   * @param string $title
   *   The page title.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved page.
   */
  protected function page(string $title): NodeInterface {
    $node = Node::create([
      'type' => 'page',
      'title' => $title,
      'status' => NodeInterface::PUBLISHED,
      'langcode' => 'en',
    ]);
    $node->save();
    return $node;
  }

  /**
   * Sets the request path and, optionally, the current node.
   *
   * @param string $path
   *   The request path.
   * @param \Drupal\node\NodeInterface|null $page
   *   The node for the current route.
   * @param string|null $routeName
   *   The route name. Defaults to the node canonical route when a page is set.
   */
  protected function setCurrentPage(string $path, ?NodeInterface $page = NULL, ?string $routeName = NULL): void {
    $stack = $this->container->get('request_stack');
    if (!$stack instanceof RequestStack) {
      throw new \RuntimeException('The request stack is missing.');
    }

    $current = $stack->getCurrentRequest();
    $request = Request::create($path);
    if ($current instanceof Request && $current->hasSession()) {
      $request->setSession($current->getSession());
    }

    $routeName ??= $page instanceof NodeInterface ? 'entity.node.canonical' : '<none>';
    $request->attributes->set('_route', $routeName);
    $routePath = $page instanceof NodeInterface ? '/node/{node}' : '/_test';
    $request->attributes->set('_route_object', new Route($routePath));
    if ($page instanceof NodeInterface) {
      $request->attributes->set('node', $page);
    }
    $stack->push($request);
  }

  /**
   * Collects node titles from a view-multiple render array.
   *
   * @param array<mixed> $build
   *   The render array.
   *
   * @return string[]
   *   Node titles.
   */
  protected function titles(array $build): array {
    $titles = [];
    foreach ($build as $item) {
      if (!is_array($item) || !isset($item['#node'])) {
        continue;
      }
      if (!$item['#node'] instanceof NodeInterface) {
        continue;
      }
      $titles[] = (string) $item['#node']->label();
    }
    return $titles;
  }

}
