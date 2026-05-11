<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\helfi_tpr_config\Hook\TprUnitRenderHooks;
use Drupal\KernelTests\KernelTestBase;
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
   * Builds a $variables array shaped like preprocess_views_view input.
   *
   * @return array<string, mixed>
   *   Preprocess variables.
   */
  private function buildVariables(Unit $unit, int $totalRows): array {
    $row = new \stdClass();
    $row->_entity = $unit;
    $view = new \stdClass();
    $view->result = [$row];
    return [
      'view' => $view,
      'total_rows' => $totalRows,
    ];
  }

  /**
   * Single result triggers count suppression and h3 heading.
   */
  public function testPreprocessSingleResult(): void {
    /** @var \Drupal\helfi_tpr\Entity\Unit $unit */
    $unit = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('tpr_unit')
      ->create(['id' => 'test-unit']);
    $variables = $this->buildVariables($unit, 1);

    (new TprUnitRenderHooks())->preprocessServiceUnitsView($variables);

    $this->assertFalse($variables['show_count_container']);
    $this->assertSame('h3', $unit->card_heading_level);
  }

  /**
   * Card heading level on the entity is appended to render cache keys.
   */
  public function testBuildDefaultsAlterAppendsCacheKey(): void {
    /** @var \Drupal\helfi_tpr\Entity\Unit $unit */
    $unit = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('tpr_unit')
      ->create(['id' => 'test-unit']);
    // @phpstan-ignore-next-line property.notFound
    $unit->card_heading_level = 'h3';
    $build = ['#cache' => ['keys' => ['entity_view', 'tpr_unit']]];

    (new TprUnitRenderHooks())->buildDefaultsAlter($build, $unit, 'teaser_with_image');

    $this->assertSame(
      ['entity_view', 'tpr_unit', 'card_heading_level:h3'],
      $build['#cache']['keys'],
    );
  }

}
