<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel;

use Drupal\helfi_api_base\Features\FeatureManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the news list fixture data.
 */
#[Group('helfi_paragraphs_news_list')]
#[RunTestsInSeparateProcesses]
class FixtureTest extends KernelTestBase {

  /**
   * Enables or disables the mock responses feature.
   *
   * @param bool $enabled
   *   Whether mock responses are used.
   */
  private function setUseMockResponses(bool $enabled) : void {
    $featureManager = $this->container->get(FeatureManagerInterface::class);

    if ($enabled) {
      $featureManager->enableFeature(FeatureManagerInterface::USE_MOCK_RESPONSES);
      return;
    }
    $featureManager->disableFeature(FeatureManagerInterface::USE_MOCK_RESPONSES);
  }

  /**
   * Tests that news are loaded from fixtures when mock responses are enabled.
   */
  public function testNewsFromFixtures() : void {
    $this->mockEtusivuElasticClient([]);
    $this->setUseMockResponses(TRUE);

    $storage = $this->container->get('entity_type.manager')->getStorage('helfi_news');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('search_api_language', 'fi')
      ->range(0, 4)
      ->execute();
    $this->assertCount(4, $ids, 'Four Finnish news are returned.');

    $entities = $storage->loadMultiple($ids);
    $this->assertCount(4, $entities, 'Only the queried news are loaded.');
    foreach ($entities as $id => $entity) {
      $this->assertStringEndsWith(':fi', $id, 'Loaded news are in Finnish.');
    }

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('search_api_language', 'en')
      ->condition('tags_uuid', ['8b2f1e0a-5c3d-4f6a-9b7e-1d2c3a4b5c6d'], 'IN')
      ->execute();
    $this->assertCount(4, $ids, 'News are filtered by tag.');

    $term = $this->container->get('entity_type.manager')
      ->getStorage('helfi_news_tags')
      ->load('8b2f1e0a-5c3d-4f6a-9b7e-1d2c3a4b5c6d:en');
    $this->assertEquals('Culture', $term?->label(), 'The tag is loaded from fixtures.');
  }

  /**
   * Tests that fixtures are not used when mock responses are disabled.
   */
  public function testFixturesDisabled() : void {
    $this->mockEtusivuElasticClient([
      $this->createElasticsearchResponse(['hits' => ['hits' => []]]),
    ]);
    $this->setUseMockResponses(FALSE);

    $ids = $this->container->get('entity_type.manager')
      ->getStorage('helfi_news')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $this->assertEmpty($ids, 'News are queried from Elasticsearch.');
  }

}
