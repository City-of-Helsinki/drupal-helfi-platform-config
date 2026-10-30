<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\helfi_users\FavoriteStorage;
use Drupal\helfi_users\Hook\FavoriteHooks;
use Drupal\helfi_users\Plugin\Action\AddFavorite;
use Drupal\helfi_users\Plugin\Action\FavoriteActionBase;
use Drupal\helfi_users\Plugin\Action\RemoveFavorite;
use Drupal\helfi_users\Plugin\Derivative\FavoriteActionDeriver;
use Drupal\helfi_users\Plugin\views\field\Favorite;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the favorite views field and bulk actions.
 */
#[CoversClass(Favorite::class)]
#[CoversClass(FavoriteHooks::class)]
#[CoversClass(AddFavorite::class)]
#[CoversClass(RemoveFavorite::class)]
#[CoversClass(FavoriteActionBase::class)]
#[CoversClass(FavoriteActionDeriver::class)]
#[Group('helfi_users')]
class FavoriteViewsTest extends FavoriteKernelTestBase {

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
    'language',
    'views',
    'views_bulk_operations',
    'helfi_users',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['views']);

    View::create([
      'id' => 'favorite_test',
      'label' => 'Favorite test',
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'access' => ['type' => 'none'],
            'cache' => ['type' => 'tag'],
            'fields' => [
              'helfi_favorite' => [
                'id' => 'helfi_favorite',
                'table' => 'node_field_data',
                'field' => 'helfi_favorite',
                'plugin_id' => 'helfi_favorite',
              ],
            ],
            'style' => [
              'type' => 'table',
              'options' => [
                'columns' => ['helfi_favorite' => 'helfi_favorite'],
                'info' => [
                  'helfi_favorite' => [
                    'sortable' => TRUE,
                    'default_sort_order' => 'asc',
                  ],
                ],
                'default' => 'helfi_favorite',
                'override' => TRUE,
              ],
            ],
            'row' => ['type' => 'fields'],
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Executes the test view sorted by favorite.
   *
   * @param string $order
   *   The sort order.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executed view.
   */
  private function executeView(string $order): ViewExecutable {
    $view = Views::getView('favorite_test');
    $view->setRequest(Request::create('/', 'GET', ['order' => 'helfi_favorite', 'sort' => $order]));
    $view->execute();
    return $view;
  }

  /**
   * Gets the translation keys of the view rows.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   *
   * @return string[]
   *   The entity ID and langcode pairs in row order.
   */
  private function getRowKeys(ViewExecutable $view): array {
    $keys = [];
    foreach ($view->result as $row) {
      $keys[] = $row->_entity?->id() . ':' . $view->field['helfi_favorite']->getValue($row, 'langcode');
    }
    return $keys;
  }

  /**
   * Tests that the field is added only to installed favorite entity types.
   */
  public function testFieldIsAddedToInstalledEntityTypes(): void {
    $data = Views::viewsData();

    $this->assertEquals('helfi_favorite', $data->get('node_field_data')['helfi_favorite']['field']['id'] ?? NULL, 'Nodes have the favorite field.');
    $this->assertArrayNotHasKey('helfi_favorite', $data->get('users_field_data'), 'Users do not have the favorite field.');
  }

  /**
   * Tests the sorting.
   */
  public function testSorting(): void {
    $user = $this->createEditor();
    $other = $this->createEditor();
    $this->setCurrentUser($user);
    $otherFavorite = $this->createPage();
    $favorite = $this->addFinnishTranslation($this->createPage());
    $this->createPage();
    $storage = $this->container->get(FavoriteStorage::class);
    $storage->toggle($user, $favorite);
    $storage->toggle($other, $favorite);
    $storage->toggle($other, $otherFavorite);

    $view = $this->executeView('asc');
    $keys = $this->getRowKeys($view);
    $this->assertCount(4, $keys, 'Sorting lists each translation once.');
    $this->assertEquals($favorite->id() . ':fi', $keys[0], 'Ascending sort lists the user favorite translations first.');
    $this->assertEquals('none', $view->display_handler->getOption('cache')['type'], 'Sorting disables results caching.');

    $keys = $this->getRowKeys($this->executeView('desc'));
    $this->assertEquals($favorite->id() . ':fi', end($keys), 'Descending sort lists the user favorite translations last.');
  }

  /**
   * Tests that the star in the listing toggles the favorite.
   */
  public function testListingToggleTogglesFavorite(): void {
    $user = $this->createEditor();
    $this->setCurrentUser($user);
    $node = $this->createPage();
    $storage = $this->container->get(FavoriteStorage::class);

    $build = Views::getView('favorite_test')->preview();
    $html = $this->clickFavoriteToggle((string) $this->container->get('renderer')->renderRoot($build));
    $this->assertStringContainsString('favorite-toggle--active', $html, 'The swapped star is active.');
    $this->assertTrue($storage->isFavorite($user, $node), 'The first click adds the favorite.');

    $this->clickFavoriteToggle($html);
    $this->assertFalse($storage->isFavorite($user, $node), 'The second click removes the favorite.');
  }

  /**
   * Tests that an editor can add and remove a favorite with the bulk actions.
   */
  public function testEditorCanAddAndRemoveFavoriteWithBulkActions(): void {
    $user = $this->createEditor();
    $this->setCurrentUser($user);
    $node = $this->createPage();
    $storage = $this->container->get(FavoriteStorage::class);
    $actionManager = $this->container->get('plugin.manager.action');
    $add = $actionManager->createInstance('helfi_favorite_add:node');
    $remove = $actionManager->createInstance('helfi_favorite_remove:node');

    $this->assertTrue($add->access($node, $user), 'The editor can use the add action.');
    $this->assertTrue($remove->access($node, $user), 'The editor can use the remove action.');

    $add->execute($node);
    $this->assertTrue($storage->isFavorite($user, $node), 'The add action adds the favorite.');

    $remove->execute($node);
    $this->assertFalse($storage->isFavorite($user, $node), 'The remove action removes the favorite.');
  }

}
