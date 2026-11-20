<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the news list fixture data.
 */
#[Group('helfi_paragraphs_news_list')]
#[RunTestsInSeparateProcesses]
class FixtureTest extends KernelTestBase {

  use EnvironmentResolverTrait;

  /**
   * Sets the active environment.
   *
   * @param \Drupal\helfi_api_base\Environment\EnvironmentEnum $environment
   *   The environment.
   */
  private function setEnvironment(EnvironmentEnum $environment) : void {
    $this->setActiveProject(Project::ETUSIVU, $environment);
    // Reset the cached active environment.
    $this->container->set('helfi_api_base.environment_resolver', NULL);
  }

  /**
   * Enables or disables the fixture setting.
   *
   * @param bool $enabled
   *   Whether fixtures are used.
   */
  private function setUseFixtures(bool $enabled) : void {
    $this->config('helfi_paragraphs_news_list.settings')
      ->set('use_fixtures', $enabled)
      ->save();
  }

  /**
   * Tests that news are loaded from fixtures on local environment.
   */
  public function testNewsFromFixtures() : void {
    $this->mockEtusivuElasticClient([]);
    $this->setEnvironment(EnvironmentEnum::Local);
    $this->setUseFixtures(TRUE);

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
   * Tests that fixtures are not used outside local environment.
   */
  public function testFixturesOutsideLocal() : void {
    $this->mockEtusivuElasticClient([
      $this->createElasticsearchResponse(['hits' => ['hits' => []]]),
    ]);
    $this->setEnvironment(EnvironmentEnum::Test);
    $this->setUseFixtures(TRUE);

    $ids = $this->container->get('entity_type.manager')
      ->getStorage('helfi_news')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $this->assertEmpty($ids, 'News are queried from Elasticsearch.');
  }

  /**
   * Tests that fixtures are not used when the setting is disabled.
   */
  public function testFixturesDisabled() : void {
    $this->mockEtusivuElasticClient([
      $this->createElasticsearchResponse(['hits' => ['hits' => []]]),
    ]);
    $this->setEnvironment(EnvironmentEnum::Local);
    $this->setUseFixtures(FALSE);

    $ids = $this->container->get('entity_type.manager')
      ->getStorage('helfi_news')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $this->assertEmpty($ids, 'News are queried from Elasticsearch.');
  }

}
