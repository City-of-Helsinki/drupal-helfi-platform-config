<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\helfi_users\FavoriteStorage;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the user content views argument.
 */
#[Group('helfi_users')]
class UserContentArgumentTest extends FavoriteKernelTestBase {

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
   * The editor.
   */
  private UserInterface $user;

  /**
   * Another editor.
   */
  private UserInterface $other;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['views']);
    $this->user = $this->createEditor();
    $this->other = $this->createEditor();

    View::create([
      'id' => 'user_content_test',
      'label' => 'User content test',
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
            'arguments' => [
              'helfi_user_content' => [
                'id' => 'helfi_user_content',
                'table' => 'node_field_data',
                'field' => 'helfi_user_content',
                'plugin_id' => 'helfi_user_content',
                'default_action' => 'not found',
                'exception' => ['value' => 'all'],
              ],
            ],
            'fields' => [
              'helfi_favorite' => [
                'id' => 'helfi_favorite',
                'table' => 'node_field_data',
                'field' => 'helfi_favorite',
                'plugin_id' => 'helfi_favorite',
              ],
            ],
            'style' => ['type' => 'default'],
            'row' => ['type' => 'fields'],
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Creates a page last edited by the user.
   *
   * @param \Drupal\user\UserInterface $editor
   *   The last editor.
   *
   * @return \Drupal\node\NodeInterface
   *   The page node.
   */
  private function createEditedPage(UserInterface $editor): NodeInterface {
    $node = Node::create([
      'type' => 'page',
      'title' => $this->randomMachineName(),
      'uid' => $editor->id(),
      'revision_uid' => $editor->id(),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Executes the test view as the user.
   *
   * @param string $argument
   *   The view argument.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executed view.
   */
  private function executeView(string $argument): ViewExecutable {
    $this->setCurrentUser($this->user);
    $view = Views::getView('user_content_test');
    $view->setArguments([$argument]);
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
   *   The entity ID and langcode pairs sorted.
   */
  private function getRowKeys(ViewExecutable $view): array {
    $keys = [];
    foreach ($view->result as $row) {
      $keys[] = $row->_entity?->id() . ':' . $view->field['helfi_favorite']->getValue($row, 'langcode');
    }
    sort($keys);
    return $keys;
  }

  /**
   * Tests that the argument is added to nodes.
   */
  public function testArgumentIsAddedToNodes(): void {
    $argument = Views::viewsData()->get('node_field_data')['helfi_user_content']['argument'] ?? [];

    $this->assertEquals('helfi_user_content', $argument['id'] ?? NULL, 'Nodes have the user content argument.');
    $this->assertEquals('node_revision', $argument['revision_table'] ?? NULL, 'The argument uses the node revision table.');
    $this->assertEquals('revision_uid', $argument['revision_user_field'] ?? NULL, 'The argument uses the revision user field.');
  }

  /**
   * Tests that only the current user's edited and favorite content is listed.
   */
  public function testUserContentIsListed(): void {
    $edited = $this->createEditedPage($this->user);
    $favorite = $this->createEditedPage($this->other);
    $favoriteTranslation = $this->addFinnishTranslation($favorite);
    $this->createEditedPage($this->other);
    $this->container->get(FavoriteStorage::class)->add($this->user, $favoriteTranslation);

    $view = $this->executeView((string) $this->user->id());

    $expected = [
      $edited->id() . ':en',
      $favorite->id() . ':fi',
    ];
    sort($expected);
    $this->assertEquals($expected, $this->getRowKeys($view), 'Only edited content and favorite translations are listed.');
    $this->assertEquals('none', $view->display_handler->getOption('cache')['type'], 'The argument disables results caching.');

    $this->assertEmpty($this->executeView((string) $this->other->id())->result, 'Content of another user is not listed.');
    $this->assertEmpty($this->executeView('all')->result, 'The exception value does not list all content.');
  }

}
