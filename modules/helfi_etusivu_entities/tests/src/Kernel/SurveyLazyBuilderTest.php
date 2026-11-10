<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\helfi_etusivu_entities\SurveyLazyBuilder;
use Drupal\helfi_node_survey\Entity\Survey;
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
   * Tests that survey nodes use the bundle class.
   */
  public function testBundleClass(): void {
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create(['type' => 'survey']);
    $this->assertInstanceOf(Survey::class, $node);

    // Only Etusivu has the field.
    $this->assertFalse($node->publishExternally());
    $this->assertSame(['helfi_external_entity_survey'], $node->getExternalCacheTags());
  }

  /**
   * Tests populating a survey from a remote survey.
   */
  public function testPopulateFromRemoteEntity(): void {
    $entityTypeManager = $this->container->get('entity_type.manager');
    $remote = $entityTypeManager->getStorage('helfi_surveys')->create([
      'uuid' => 'c9ee55c3-9ca5-4c53-900e-82b6d6928a98',
      'langcode' => 'sv',
      'title' => 'Remote <script>alert(1)</script>',
      'body' => '<em onclick="x">Body</em><p>Text</p>',
      'status' => NodeInterface::PUBLISHED,
      'survey_link_text' => 'Answer',
      'survey_link_url' => 'https://example.com/survey',
    ]);
    $this->assertInstanceOf(FieldableEntityInterface::class, $remote);

    $node = $entityTypeManager->getStorage('node')->create(['type' => 'survey']);
    $this->assertInstanceOf(Survey::class, $node);
    $this->assertSame($node, $node->populateFromRemoteEntity($remote));

    $this->assertTrue($node->isNew());
    $this->assertSame('c9ee55c3-9ca5-4c53-900e-82b6d6928a98', $node->uuid());
    $this->assertSame('sv', $node->get('langcode')->value);
    $this->assertSame('Remote alert(1)', $node->label());
    $this->assertSame('<em>Body</em>Text', $node->get('body')->value);
    $this->assertTrue($node->isPublished());
    $this->assertSame('https://example.com/survey', $node->get('field_survey_link')->uri);
    $this->assertSame('Answer', $node->get('field_survey_link')->title);
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
