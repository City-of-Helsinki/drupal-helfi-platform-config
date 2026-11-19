<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Controller;

use Drupal\Core\Routing\RouteMatch;
use Drupal\helfi_platform_config\Controller\TranslationOverviewController;
use Drupal\helfi_platform_config\Translation\OutdatedTranslationChecker;
use Drupal\node\Entity\Node;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\helfi_platform_config\Kernel\Translation\OutdatedTranslationTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the translations overview with pending revisions.
 */
#[CoversClass(TranslationOverviewController::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class TranslationOverviewPendingRevisionTest extends OutdatedTranslationTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->enableModules(['workflows', 'content_moderation']);
    $this->installEntitySchema('content_moderation_state');
    $this->installConfig(['content_moderation']);

    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'page');

    $this->container->get('router.builder')->rebuild();
    $this->setUpCurrentUser(permissions: ['access content', 'translate any entity', 'bypass node access']);
  }

  /**
   * Tests that a pending revision of a translation is used.
   */
  public function testPendingRevision(): void {
    $node = Node::create([
      'type' => 'page',
      'title' => 'fi',
      'langcode' => 'fi',
      'changed_at' => self::ORIGINAL,
      'moderation_state' => 'published',
    ]);
    $node->addTranslation('sv', [
      'title' => 'sv',
      'changed_at' => self::ORIGINAL - 21 * self::DAY,
      'moderation_state' => 'published',
    ]);
    $node->save();

    // A draft brings the translation up to date. The published translation
    // stays outdated.
    $draft = $node->getTranslation('sv');
    $draft->setNewRevision();
    $draft->set('changed_at', self::ORIGINAL);
    $draft->set('moderation_state', 'draft');
    $draft->save();

    $node = Node::load($node->id());
    $this->assertInstanceOf(Node::class, $node);
    $this->assertSame(['sv' => 21], $this->container->get(OutdatedTranslationChecker::class)->getOutdatedTranslations($node));

    $route = $this->container->get('router.route_provider')->getRouteByName('entity.node.content_translation_overview');
    $controller = $this->container->get('class_resolver')->getInstanceFromDefinition(TranslationOverviewController::class);
    $build = $controller->overview(new RouteMatch('entity.node.content_translation_overview', $route, ['node' => $node]), 'node');
    $rows = array_combine(
      array_keys($this->container->get('language_manager')->getLanguages()),
      $build['content_translation_overview']['#rows'],
    );

    // The row of the translation shows the draft, like the rest of the row.
    $this->assertSame($this->container->get('date.formatter')->format(self::ORIGINAL, 'long'), $rows['sv'][3]);
  }

}
