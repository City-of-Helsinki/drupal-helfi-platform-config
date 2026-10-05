<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\view;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\EntityNotifications\EntityNotification;
use Drupal\helfi_platform_config\EntityNotifications\EntityNotificationProviderInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\helfi_platform_config\Traits\InstallConfigEntityTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the entity notifications views field in the dashboard view.
 */
#[Group('helfi_users')]
#[RunTestsInSeparateProcesses]
class EntityNotificationsFieldTest extends KernelTestBase {

  use InstallConfigEntityTrait;
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
    'views',
    'views_bulk_operations',
    'language',
  ];

  /**
   * Notification labels keyed by entity ID and langcode ("id:langcode").
   *
   * @var array<string, list<string>>
   */
  private array $notifications = [];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $container->register('helfi_platform_config_test.notification_provider')
      ->setSynthetic(TRUE)
      ->addTag('helfi_platform_config.entity_notification_provider');

    // register() runs again on every container rebuild.
    $container->set('helfi_platform_config_test.notification_provider', new class ($this->notifications) implements EntityNotificationProviderInterface {

      /**
       * @param array<string, list<string>> $notifications
       *   Notification labels keyed by entity ID and langcode.
       */
      public function __construct(private array &$notifications) {}

      /**
       * {@inheritdoc}
       */
      public function getNotifications(ContentEntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
        return array_map(
          static fn (string $label) => new EntityNotification($label, Url::fromRoute('<front>')),
          $this->notifications[$entity->id() . ':' . $entity->language()->getId()] ?? [],
        );
      }

    });
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'filter', 'views', 'language']);

    // Install dashboard view.
    $this->installConfigEntity('helfi_users', 'views.view.dashboard_your_content');
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
  }

  /**
   * Tests that notifications render in the title column.
   */
  public function testNotificationsRenderUnderTitle(): void {
    $user = $this->setUpCurrentUser(permissions: ['access content', 'administer users']);

    foreach (['First', 'Second'] as $title) {
      $nodes[$title] = Node::create(['type' => 'page', 'title' => $title, 'uid' => $user->id()]);
      $nodes[$title]->save();
    }

    $this->notifications[$nodes['Second']->id() . ':en'][] = 'notification';

    $view = Views::getView('dashboard_your_content');
    $this->assertNotNull($view);

    $build = $view->buildRenderable('your_content_block', [$user->id()]);
    $this->assertIsArray($build);

    $html = (string) $this->container->get(RendererInterface::class)->renderInIsolation($build);

    $this->assertSame(1, substr_count($html, 'class="entity-notifications"'));
    $this->assertMatchesRegularExpression('#<td[^>]*views-field-title[^>]*>\s*<a[^>]*>Second</a>\s*<ul class="entity-notifications">.*notification.*</ul>\s*</td>#s', $html);
  }

}
