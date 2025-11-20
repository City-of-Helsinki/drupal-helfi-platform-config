<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel\Plugin\DebugDataItem;

use Drupal\helfi_api_base\DebugDataItemPluginManager;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_etusivu_entities\Plugin\DebugDataItem\ApiAvailabilityBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_paragraphs_news_list\Kernel\KernelTestBase;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;

/**
 * @coversDefaultClass \Drupal\helfi_etusivu_entities\Plugin\DebugDataItem\ApiAvailabilityBase
 * @group helfi_etusivu_entities
 */
class ApiAvailabilityTest extends KernelTestBase {

  use EnvironmentResolverTrait;
  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'helfi_api_base',
    'external_entities',
    'helfi_etusivu_entities',
  ];

  /**
   * The plugins to test.
   *
   * @var string[]
   */
  protected array $plugins = [
    'etusivu_entities_announcement',
    'etusivu_entities_survey',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['helfi_etusivu_entities']);
    $this->setActiveProject(Project::ETUSIVU, EnvironmentEnum::Local);
  }

  /**
   * Gets the SUT.
   *
   * @param string $plugin
   *   The plugin id.
   * @param array $responses
   *   The responses.
   *
   * @return \Drupal\helfi_etusivu_entities\Plugin\DebugDataItem\ApiAvailabilityBase
   *   The SUT.
   */
  public function getSut(string $plugin, array $responses) : ApiAvailabilityBase {
    $client = $this->createMockHttpClient($responses);
    $this->container->set('http_client', $client);

    return $this->container->get(DebugDataItemPluginManager::class)
      ->createInstance($plugin);
  }

  /**
   * Make sure check() fails on invalid responses.
   */
  public function testInvalidResponses(): void {
    foreach ($this->plugins as $plugin) {
      $sut = $this->getSut($plugin, [new TransferException()]);
      $this->assertFalse($sut->check());
    }
  }

  /**
   * Tests a successful check().
   */
  public function testCheck(): void {
    foreach ($this->plugins as $plugin) {
      $sut = $this->getSut($plugin, [new Response(body: json_encode(['meta' => 1]))]);
      $this->assertTrue($sut->check());
    }
  }

}
