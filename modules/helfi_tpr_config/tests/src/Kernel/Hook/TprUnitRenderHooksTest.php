<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\helfi_tpr_config\Hook\TprUnitRenderHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests TprUnitRenderHooks.
 */
#[Group('helfi_tpr_config')]
#[RunTestsInSeparateProcesses]
class TprUnitRenderHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_tpr',
    'user',
    'media',
    'link',
    'text',
    'address',
    'menu_link_content',
    'telephone',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('tpr_unit');
  }

  /**
   * Single result sets the h3 heading on the entity in views_post_execute.
   */
  public function testViewsPostExecute(): void {
    $unit = $this->createUnit();
    $view = $this->buildView($unit, 2);

    (new TprUnitRenderHooks())->viewsPostExecute($view);

    // Multiple result, no change.
    $this->assertFalse(isset($unit->card_heading_level));

    $view = $this->buildView($unit, 1);

    (new TprUnitRenderHooks())->viewsPostExecute($view);

    // Single result sets the h3 heading on the entity in views_post_execute.
    $this->assertSame('h3', $unit->card_heading_level);

    $build = ['#cache' => ['keys' => ['entity_view', 'tpr_unit']]];

    (new TprUnitRenderHooks())->buildDefaultsAlter($build, $unit, 'teaser_with_image');

    // Card heading level on the entity is appended to render cache keys.
    $this->assertSame(
      ['entity_view', 'tpr_unit', 'card_heading_level:h3'],
      $build['#cache']['keys'],
    );
  }

  /**
   * A single result suppresses the count container in the view template.
   */
  public function testPreprocessSingleResult(): void {
    $unit = $this->createUnit();
    $variables = ['view' => $this->buildView($unit, 1)];

    (new TprUnitRenderHooks())->preprocessServiceUnitsView($variables);

    $this->assertFalse($variables['show_count_container']);
  }

  /**
   * Creates a tpr_unit entity.
   */
  private function createUnit(): Unit {
    /** @var \Drupal\helfi_tpr\Entity\Unit $unit */
    $unit = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('tpr_unit')
      ->create(['id' => 'test-unit']);
    return $unit;
  }

  /**
   * Builds a service_units ViewExecutable mock with the given result.
   */
  private function buildView(Unit $unit, int $totalRows, string $id = 'service_units'): ViewExecutable {
    $row = new ResultRow();
    $row->_entity = $unit;
    $view = $this->createMock(ViewExecutable::class);
    $view->method('id')->willReturn($id);
    $view->total_rows = $totalRows;
    $view->result = [$row];
    return $view;
  }

}
