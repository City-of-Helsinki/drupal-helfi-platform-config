<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Kernel;

use Drupal\Core\Render\RendererInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the helfi_ai theme hooks.
 */
#[Group('helfi_ai')]
class ThemeHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'diff',
    'helfi_api_base',
    'config_rewrite',
    'node',
    'language',
    'helfi_ai',
  ];

  /**
   * The renderer.
   */
  private RendererInterface $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Renders the given build in isolation.
   *
   * @param array<string, mixed> $build
   *   The render array.
   *
   * @return string
   *   The rendered markup.
   */
  private function renderBuild(array $build): string {
    return (string) $this->renderer->renderInIsolation($build);
  }

  /**
   * Test that the theme hook and templates work.
   */
  public function testTitleSuggestionsRender(): void {
    $markup = $this->renderBuild([
      '#theme' => 'ai_title_suggestions',
      '#suggestions' => ['First title', 'Second title'],
      '#original_title' => 'Original page title',
    ]);

    $this->assertStringContainsString('ai-title-suggestions', $markup);
    $this->assertStringContainsString('Original page title', $markup);
    $this->assertStringContainsString('value="First title"', $markup);
    $this->assertStringContainsString('value="Second title"', $markup);
    $this->assertMatchesRegularExpression('/<button[^>]*ai-title-suggestions__apply[^>]*disabled/', $markup, 'The apply button is disabled until a suggestion is selected.');

    $markup = $this->renderBuild([
      '#theme' => 'ai_title_suggestions',
      '#suggestions' => ['First title'],
    ]);

    $this->assertStringContainsString('<p class="ai-title-suggestions__original-value">–</p>', $markup, 'A missing original title is shown as a dash.');

    $markup = $this->renderBuild([
      '#theme' => 'ai_message',
      '#text' => 'Summary generated.',
    ]);

    $this->assertStringContainsString('<p>Summary generated.</p>', $markup);
  }

}
