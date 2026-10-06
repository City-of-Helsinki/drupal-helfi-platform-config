<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Hooks;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Tests the 'changed_at' views integration.
 */
#[RunTestsInSeparateProcesses]
final class ChangedAtViewsHooksTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'helfi_platform_config_update_test',
    'language',
    'node',
    'system',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node']);

    // 'page' opts in via helfi_platform_config_update_test's
    // hook_helfi_changed_at_field_bundles().
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    ConfigurableLanguage::createFromLangcode('fi')->save();
  }

  /**
   * Tests that the field is exposed to views.
   */
  public function testViewsData(): void {
    $viewsData = $this->container->get('views.views_data');
    $data = $viewsData->get('node__changed_at');

    $this->assertSame([
      'left_field' => 'nid',
      'field' => 'entity_id',
      'extra' => [
        ['field' => 'deleted', 'value' => 0, 'numeric' => TRUE],
        ['left_field' => 'langcode', 'field' => 'langcode'],
      ],
    ], $data['table']['join']['node_field_data']);

    $this->assertSame('field', $data['changed_at']['field']['id']);
    $this->assertFalse($data['changed_at']['field']['is revision']);
    $this->assertSame('date', $data['changed_at_value']['sort']['id']);
    $this->assertSame('date', $data['changed_at_value']['filter']['id']);
    $this->assertSame('date', $data['changed_at_value']['argument']['id']);

    $data = $viewsData->get('node_revision__changed_at');

    $this->assertSame([
      'left_field' => 'vid',
      'field' => 'revision_id',
      'extra' => [
        ['field' => 'deleted', 'value' => 0, 'numeric' => TRUE],
        ['left_field' => 'langcode', 'field' => 'langcode'],
      ],
    ], $data['table']['join']['node_field_revision']);

    $this->assertSame('field', $data['changed_at__revision_id']['field']['id']);
    $this->assertTrue($data['changed_at__revision_id']['field']['is revision']);
    $this->assertSame('date', $data['changed_at_value']['sort']['id']);
    $this->assertSame('date', $data['changed_at_value']['filter']['id']);
    $this->assertSame('date', $data['changed_at_value']['argument']['id']);
  }

  /**
   * Creates and executes a view listing nodes by 'changed_at'.
   *
   * @param string $baseTable
   *   The base table.
   * @param string $idField
   *   The id field on the base table.
   * @param string $fieldTable
   *   The 'changed_at' table.
   * @param string $fieldAlias
   *   The 'changed_at' field alias.
   * @param string $order
   *   The sort order.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executed view.
   */
  private function executeView(string $baseTable, string $idField, string $fieldTable, string $fieldAlias, string $order): ViewExecutable {
    View::create([
      'id' => 'changed_at_test',
      'base_table' => $baseTable,
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_options' => [
            'fields' => [
              'id' => [
                'id' => 'id',
                'table' => $baseTable,
                'field' => $idField,
                'plugin_id' => 'field',
              ],
              'langcode' => [
                'id' => 'langcode',
                'table' => $baseTable,
                'field' => 'langcode',
                'plugin_id' => 'field',
              ],
              'changed_at' => [
                'id' => 'changed_at',
                'table' => $fieldTable,
                'field' => $fieldAlias,
                'plugin_id' => 'field',
                'type' => 'timestamp',
                'settings' => [
                  'date_format' => 'custom',
                  'custom_date_format' => 'Y-m-d H:i',
                  'timezone' => 'UTC',
                ],
              ],
            ],
            'sorts' => [
              'changed_at_value' => [
                'id' => 'changed_at_value',
                'table' => $fieldTable,
                'field' => 'changed_at_value',
                'plugin_id' => 'date',
                'order' => $order,
                'granularity' => 'second',
              ],
            ],
          ],
        ],
      ],
    ])->save();

    $view = Views::getView('changed_at_test');
    $this->assertInstanceOf(ViewExecutable::class, $view);
    $view->execute();

    return $view;
  }

  /**
   * Tests sorting and formatting per translation.
   */
  public function testSortAndFormat(): void {
    $node1 = Node::create(['type' => 'page', 'title' => 'first', 'langcode' => 'en']);
    $node1->set('changed_at', strtotime('2026-01-02 10:00:00 UTC'));
    $node1->addTranslation('fi', [
      'title' => 'eka',
      'changed_at' => strtotime('2026-03-04 10:00:00 UTC'),
    ]);
    $node1->save();

    $node2 = Node::create(['type' => 'page', 'title' => 'second', 'langcode' => 'en']);
    $node2->set('changed_at', strtotime('2026-02-03 10:00:00 UTC'));
    $node2->save();

    $expected = [
      [$node1->id(), 'en', '2026-01-02 10:00'],
      [$node2->id(), 'en', '2026-02-03 10:00'],
      [$node1->id(), 'fi', '2026-03-04 10:00'],
    ];

    foreach (['ASC' => $expected, 'DESC' => array_reverse($expected)] as $order => $rows) {
      $view = $this->executeView('node_field_data', 'nid', 'node__changed_at', 'changed_at', $order);
      $this->assertEquals($rows, $this->getRows($view), "Sort order: $order");
      View::load('changed_at_test')->delete();
    }
  }

  /**
   * Tests sorting and formatting per revision and translation.
   */
  public function testRevisionSortAndFormat(): void {
    $node = Node::create(['type' => 'page', 'title' => 'first', 'langcode' => 'en']);
    $node->set('changed_at', strtotime('2026-01-02 10:00:00 UTC'));
    $node->save();
    $firstRevision = $node->getRevisionId();

    $node->setNewRevision();
    $node->set('changed_at', strtotime('2026-03-04 10:00:00 UTC'));
    $node->addTranslation('fi', [
      'title' => 'eka',
      'changed_at' => strtotime('2026-02-03 10:00:00 UTC'),
    ]);
    $node->save();
    $secondRevision = $node->getRevisionId();

    $expected = [
      [$firstRevision, 'en', '2026-01-02 10:00'],
      [$secondRevision, 'fi', '2026-02-03 10:00'],
      [$secondRevision, 'en', '2026-03-04 10:00'],
    ];

    foreach (['ASC' => $expected, 'DESC' => array_reverse($expected)] as $order => $rows) {
      $view = $this->executeView('node_field_revision', 'vid', 'node_revision__changed_at', 'changed_at__revision_id', $order);
      $this->assertEquals($rows, $this->getRows($view), "Sort order: $order");
      View::load('changed_at_test')->delete();
    }
  }

  /**
   * Gets the id, langcode and formatted 'changed_at' of each result row.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   *
   * @return array<int, array<int, mixed>>
   *   The rows.
   */
  private function getRows(ViewExecutable $view): array {
    $rows = [];

    foreach ($view->result as $index => $row) {
      $rows[] = [
        $view->field['id']->getValue($row),
        $view->field['langcode']->getValue($row),
        trim(strip_tags((string) $view->style_plugin->getField($index, 'changed_at'))),
      ];
    }
    return $rows;
  }

}
