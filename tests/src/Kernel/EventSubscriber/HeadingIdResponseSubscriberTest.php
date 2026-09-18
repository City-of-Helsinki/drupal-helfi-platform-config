<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\EventSubscriber;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\helfi_platform_config\EventSubscriber\HeadingIdResponseSubscriber;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests heading id injection on real requests.
 */
#[CoversClass(HeadingIdResponseSubscriber::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class HeadingIdResponseSubscriberTest extends KernelTestBase {

  use ApiTestTrait;

  /**
   * The main content of the test page.
   */
  private const string BODY = '<main class="layout-main-wrapper">'
    . '<h2>How to Apply</h2>'
    . '<h3>Päätös</h3>'
    . '<h2 id="custom-anchor">Pretty Title</h2>'
    . '<div class="hide-from-table-of-contents"><h2>Internal widget</h2></div>'
    . '<h2>How to Apply</h2>'
    . '</main>';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['field', 'filter', 'node', 'system', 'user']);

    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    // A format without filters, so the raw markup is served as is.
    FilterFormat::create([
      'format' => 'test_html',
      'name' => 'Test HTML',
      'filters' => [],
    ])->save();

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Body',
    ])->save();

    $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('node', 'page', 'full')
      ->setComponent('body', ['type' => 'text_default', 'label' => 'hidden'])
      ->save();
  }

  /**
   * Tests that a rendered node page gets the anchors.
   */
  public function testNodePageIsRewritten(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'Heading anchors',
      'status' => 1,
      'body' => [
        'value' => self::BODY,
        'format' => 'test_html',
      ],
    ]);
    $node->save();

    $response = $this->processRequest(Request::create($node->toUrl()->toString()));
    $content = (string) $response->getContent();

    $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    $this->assertStringContainsString('<h2 id="how-to-apply" data-helfi-heading-id="" tabindex="-1">How to Apply</h2>', $content);
    $this->assertStringContainsString('<h3 id="paatos" data-helfi-heading-id="" tabindex="-1">Päätös</h3>', $content);
    // Existing id is kept as is.
    $this->assertStringContainsString('<h2 id="custom-anchor" tabindex="-1">Pretty Title</h2>', $content);
    // Hidden regions are left alone.
    $this->assertStringContainsString('<h2>Internal widget</h2>', $content);
    $this->assertStringContainsString('<h2 id="how-to-apply-2" data-helfi-heading-id="" tabindex="-1">How to Apply</h2>', $content);
  }

}
