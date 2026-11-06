<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel;

use Drupal\helfi_etusivu_entities\SurveyLazyBuilder;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests survey visibility.
 */
#[Group('helfi_platform_config')]
final class SurveyLazyBuilderTest extends LazyBuilderTestBase {

  /**
   * {@inheritdoc}
   */
  protected function builderClass(): string {
    return SurveyLazyBuilder::class;
  }

  /**
   * Tests which survey is selected for a path or content page.
   */
  public function testVisibility(): void {
    $pageA = $this->page('Page A');
    $pageB = $this->page('Page B');

    $this->survey('Everywhere', 100);
    $this->survey('On path', 200, [
      'field_survey_page_paths' => "/only-here\n/section/*",
    ]);
    $this->survey('On page', 300, [
      'field_survey_content_pages' => ['target_id' => $pageA->id()],
    ]);
    $this->survey('Hidden', 400, [
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);

    $this->setCurrentPage('/page-a', $pageA);
    $this->assertSame(['On page'], $this->surveyTitles());

    $this->setCurrentPage('/only-here', $pageB);
    $this->assertSame(['On path'], $this->surveyTitles());

    $this->setCurrentPage('/section/child', $pageB);
    $this->assertSame(['On path'], $this->surveyTitles());

    $this->setCurrentPage('/section', $pageB);
    $this->assertSame(['Everywhere'], $this->surveyTitles());

    $this->setCurrentPage('/somewhere', $pageB);
    $this->assertSame(['Everywhere'], $this->surveyTitles());

    $this->setCurrentPage('/only-here', NULL, 'system.404');
    $this->assertSame(['Everywhere'], $this->surveyTitles());
  }

  /**
   * Creates a survey.
   *
   * @param string $title
   *   The survey title.
   * @param int $publishedAt
   *   The publication timestamp. Newer surveys are considered first.
   * @param array<string, mixed> $values
   *   Field value overrides.
   */
  private function survey(string $title, int $publishedAt, array $values = []): void {
    $node = Node::create($values + [
      'type' => 'survey',
      'title' => $title,
      'status' => NodeInterface::PUBLISHED,
      'langcode' => 'en',
      'published_at' => $publishedAt,
      'field_survey_link' => 'https://example.com',
    ]);
    $node->save();
  }

  /**
   * Builds the local surveys and returns their titles.
   *
   * @return string[]
   *   Visible survey titles, in render order.
   */
  private function surveyTitles(): array {
    $builder = $this->container->get(SurveyLazyBuilder::class);
    if (!$builder instanceof SurveyLazyBuilder) {
      throw new \RuntimeException('The survey builder is missing.');
    }
    return $this->titles($builder->lazyBuild(FALSE));
  }

}
