<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Translation;

use Drupal\Core\Routing\RouteMatch;
use Drupal\helfi_platform_config\Translation\OutdatedTranslationChecker;
use Drupal\helfi_platform_config\Translation\OutdatedTranslationNotice;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;

/**
 * Tests the outdated translation notice on the node edit form.
 */
#[CoversClass(OutdatedTranslationNotice::class)]
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class OutdatedTranslationNoticeTest extends OutdatedTranslationTestBase {

  /**
   * Runs the page preprocess for the given route and translation.
   *
   * @param string $routeName
   *   The route name.
   * @param \Drupal\node\NodeInterface $translation
   *   The translation the edit form edits.
   *
   * @return array<string, mixed>
   *   The page variables.
   */
  private function preprocessPage(string $routeName, NodeInterface $translation): array {
    $notice = new OutdatedTranslationNotice(
      new RouteMatch($routeName, new Route('/node/{node}/edit'), ['node' => $translation]),
      $this->container->get(OutdatedTranslationChecker::class),
    );

    $variables = ['page' => ['content' => ['system_main' => ['#markup' => 'form']]]];
    $notice->preprocessPage($variables);

    return $variables;
  }

  /**
   * Tests the notice on an outdated translation.
   */
  public function testOutdatedTranslation(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, ['sv' => self::ORIGINAL - 21 * self::DAY]);
    $variables = $this->preprocessPage('entity.node.edit_form', $node->getTranslation('sv'));

    // The notice is shown above the form.
    $this->assertSame(['helfi_outdated_translation_notice', 'system_main'], array_keys($variables['page']['content']));

    $output = (string) $this->container->get('renderer')->renderInIsolation($variables['page']['content']['helfi_outdated_translation_notice']);
    $this->assertStringContainsString('paragraphs-counter__warning', $output);
    $this->assertStringContainsString('<strong>Check the language version</strong>', $output);
    $this->assertStringContainsString('This language version is 21 days older than the Finnish version.', $output);
  }

  /**
   * Tests that the original and up-to-date translations get no notice.
   */
  public function testNoNotice(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, [
      'sv' => self::ORIGINAL - 21 * self::DAY,
      'en' => self::ORIGINAL,
    ]);

    foreach (['fi', 'en'] as $langcode) {
      $variables = $this->preprocessPage('entity.node.edit_form', $node->getTranslation($langcode));

      $this->assertSame(['system_main'], array_keys($variables['page']['content']));
    }
  }

  /**
   * Tests that other routes are not altered.
   */
  public function testOtherRoute(): void {
    $node = $this->createTranslatedNode(self::ORIGINAL, ['sv' => self::ORIGINAL - 21 * self::DAY]);
    $variables = $this->preprocessPage('entity.node.canonical', $node->getTranslation('sv'));

    $this->assertSame(['system_main'], array_keys($variables['page']['content']));
  }

}
