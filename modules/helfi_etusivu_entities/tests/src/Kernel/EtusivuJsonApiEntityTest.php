<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Unit;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\Announcements;
use GuzzleHttp\Psr7\Response;

/**
 * Tests remote entity base classes.
 *
 * @group helfi_platform_config
 */
class EtusivuJsonApiEntityTest extends KernelTestBase {

  use EnvironmentResolverTrait;
  use ApiTestTrait;

  /**
   * Test environment.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentEnum
   */
  private EnvironmentEnum $environment = EnvironmentEnum::Local;

  /**
   * Skip schema check.
   *
   * @var bool
   */
  // phpcs:ignore
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'external_entities',
    'helfi_etusivu_entities',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['helfi_etusivu_entities']);
    $this->setActiveProject(Project::ETUSIVU, $this->environment);
  }

  /**
   * Make sure that cache is used.
   */
  public function testRequestCache(): void {
    $this->setupMockHttpClient([
      new Response(body: file_get_contents(__DIR__ . "/../../fixtures/survey.json")),
      new Response(body: json_encode(['data' => []])),
    ]);

    $entityTypeManager = $this->container->get(EntityTypeManagerInterface::class);
    $storage = $entityTypeManager->getStorage('helfi_announcements');

    // Cache should be used on seconds request.
    $this->assertNotEmpty($storage->loadMultiple());
    $this->assertNotEmpty($storage->loadMultiple());

    /** @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagInvalidator */
    $cacheTagInvalidator = $this->container->get(CacheTagsInvalidatorInterface::class);
    $cacheTagInvalidator->invalidateTags([Announcements::$customCacheTag]);

    // Cache should not be used.
    $this->assertEmpty($storage->loadMultiple());
  }

}
