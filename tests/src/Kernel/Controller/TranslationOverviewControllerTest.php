<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Controller;

use Drupal\Core\Routing\RouteMatch;
use Drupal\helfi_platform_config\Controller\TranslationOverviewController;
use Drupal\helfi_platform_config\Routing\TranslationOverviewRouteSubscriber;
use Drupal\node\NodeInterface;
use Drupal\Tests\helfi_platform_config\Kernel\Translation\OutdatedTranslationTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the translations overview with outdated translations.
 */
#[CoversClass(TranslationOverviewController::class)]
#[CoversClass(TranslationOverviewRouteSubscriber::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class TranslationOverviewControllerTest extends OutdatedTranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('router.builder')->rebuild();
    $this->setUpCurrentUser(permissions: ['access content', 'translate any entity', 'bypass node access']);
  }

  /**
   * Builds the overview of the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return array<string, mixed>
   *   The overview table, with the rows keyed by langcode.
   */
  private function buildOverview(NodeInterface $node): array {
    $route = $this->container->get('router.route_provider')->getRouteByName('entity.node.content_translation_overview');
    $controller = $this->container->get('class_resolver')->getInstanceFromDefinition(TranslationOverviewController::class);
    $build = $controller->overview(new RouteMatch('entity.node.content_translation_overview', $route, ['node' => $node]), 'node');

    $table = $build['content_translation_overview'];
    $table['#rows'] = array_combine(
      array_keys($this->container->get('language_manager')->getLanguages()),
      $table['#rows'],
    );

    return $table;
  }

  /**
   * Tests that the route uses the controller.
   */
  public function testRoute(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('entity.node.content_translation_overview');

    $this->assertSame(TranslationOverviewController::class . '::overview', $route->getDefault('_controller'));
  }

  /**
   * Tests the updated column.
   */
  public function testUpdatedColumn(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, [
      'sv' => self::ORIGINAL - 21 * self::DAY,
    ]);
    $table = $this->buildOverview($node);
    $dateFormatter = $this->container->get('date.formatter');

    // Language, translation, status, updated, operations.
    $this->assertCount(5, $table['#header']);
    $this->assertSame('Updated', (string) $table['#header'][3]);

    // The original shows the time it was last edited.
    $this->assertSame($dateFormatter->format(self::ORIGINAL, 'long'), $table['#rows']['fi'][3]);

    // The outdated translation shows the notice.
    $cell = $table['#rows']['sv'][3]['data'];
    $this->assertSame(['translation-state--outdated'], $cell['notice']['#attributes']['class']);
    $this->assertSame('Check the language version', (string) $cell['notice']['#value']);
    $this->assertSame(
      $dateFormatter->format(self::ORIGINAL - 21 * self::DAY, 'short') . ' (21 days older than Finnish)',
      (string) $cell['changed']['#value'],
    );

    // Languages without a translation have no time.
    $this->assertSame('', $table['#rows']['en'][3]);

    // The operations stay last.
    foreach ($table['#rows'] as $row) {
      $this->assertCount(5, $row);
      $this->assertSame('operations', $row[4]['data']['#type']);
    }
  }

  /**
   * Tests the column with the optional source language column.
   */
  public function testSourceLanguageColumn(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, [
      'en' => self::ORIGINAL,
      'sv' => self::ORIGINAL - 21 * self::DAY,
    ]);
    // Translating from another translation adds the source language column.
    $node->getTranslation('sv')->set('content_translation_source', 'en')->save();

    $table = $this->buildOverview($node);

    // Language, translation, source, status, updated, operations.
    $this->assertCount(6, $table['#header']);
    $this->assertSame('Updated', (string) $table['#header'][4]);
    $this->assertSame('Check the language version', (string) $table['#rows']['sv'][4]['data']['notice']['#value']);
    $this->assertSame('operations', $table['#rows']['sv'][5]['data']['#type']);
  }

  /**
   * Tests that a table the column cannot be added to is left as it is.
   */
  public function testUnexpectedTable(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, ['sv' => self::ORIGINAL - 21 * self::DAY]);
    $controller = $this->container->get('class_resolver')->getInstanceFromDefinition(TranslationOverviewController::class);
    $addUpdatedColumn = new \ReflectionMethod($controller, 'addUpdatedColumn');

    // One row per language (en, fi, sv) is expected.
    $row = ['language', 'translation', 'status', 'operations'];
    $tables = [
      'missing header' => ['#rows' => [$row, $row, $row]],
      'too few rows' => ['#header' => $row, '#rows' => [$row, $row]],
      'keyed rows' => ['#header' => $row, '#rows' => ['en' => $row, 'fi' => $row, 'sv' => $row]],
      'invalid row' => ['#header' => $row, '#rows' => [$row, $row, 'row']],
    ];
    $builds = ['missing table' => ['#title' => 'Translations']];
    foreach ($tables as $case => $table) {
      $builds[$case] = ['content_translation_overview' => $table];
    }

    foreach ($builds as $case => $build) {
      $this->assertSame($build, $addUpdatedColumn->invoke($controller, $build, $node), $case);
    }
  }

  /**
   * Tests a bundle without the 'changed_at' field.
   */
  public function testBundleWithoutField(): void {
    $node = $this->createTranslatedNode(NULL, ['sv' => NULL], 'article');
    $table = $this->buildOverview($node);

    $this->assertCount(4, $table['#header']);
    $this->assertNotContains('Updated', array_map('strval', $table['#header']));
  }

}
