<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\helfi_users\Hook\BulkOperationsHooks;
use Drupal\helfi_users\Hook\PermissionHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests restricting views bulk operations by permission.
 */
#[Group('helfi_users')]
#[RunTestsInSeparateProcesses]
class BulkOperationsHooksTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'views_bulk_operations',
    'helfi_users',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');

    // Create a superuser so test users are regular users.
    $this->createUser();

    View::create([
      'id' => 'bulk_operations_test',
      'label' => 'Bulk operations test',
      'base_table' => 'users_field_data',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'access' => ['type' => 'none'],
            'cache' => ['type' => 'none'],
            'fields' => [
              'views_bulk_operations_bulk_form' => [
                'id' => 'views_bulk_operations_bulk_form',
                'table' => 'views',
                'field' => 'views_bulk_operations_bulk_form',
                'plugin_id' => 'views_bulk_operations_bulk_form',
                'selected_actions' => [
                  ['action_id' => 'user_block_user_action'],
                ],
              ],
              'name' => [
                'id' => 'name',
                'table' => 'users_field_data',
                'field' => 'name',
                'entity_type' => 'user',
                'entity_field' => 'name',
                'plugin_id' => 'field',
              ],
            ],
            'row' => ['type' => 'fields'],
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Builds the test view and returns its field handler IDs.
   *
   * @return string[]
   *   The field handler IDs.
   */
  private function getFieldIds(): array {
    $view = Views::getView('bulk_operations_test');
    $this->assertNotNull($view);
    $view->setDisplay('default');
    $view->preExecute();
    $view->execute();
    return array_keys($view->field);
  }

  /**
   * Tests that the bulk operations field requires the permission.
   */
  public function testBulkOperationsFieldRequiresPermission(): void {
    $this->setCurrentUser($this->createUser());
    $this->assertEquals(['name'], $this->getFieldIds(), 'The bulk operations field is removed without the permission.');

    $this->setCurrentUser($this->createUser([BulkOperationsHooks::PERMISSION]));
    $this->assertEquals(['views_bulk_operations_bulk_form', 'name'], $this->getFieldIds(), 'The bulk operations field is kept with the permission.');
  }

  /**
   * Tests that the bulk operations routes require the permission.
   */
  public function testBulkOperationsRoutesRequirePermission(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('views_bulk_operations.execute_batch');
    $this->assertEquals(BulkOperationsHooks::PERMISSION, $route->getRequirement('_permission'));
  }

  /**
   * Tests that the permission is granted to the admin role.
   */
  public function testPermissionIsGrantedToAdmin(): void {
    $permissions = (new PermissionHooks())->permissions();
    $this->assertContains(BulkOperationsHooks::PERMISSION, $permissions['admin']);
  }

}
