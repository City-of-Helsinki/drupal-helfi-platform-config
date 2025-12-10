<?php

declare(strict_types=1);

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\external_entities\Entity\ExternalEntityType;
use Drupal\helfi_paragraphs_hearings\Hook\HearingsParagraphHooks;
use Drupal\helfi_paragraphs_hearings\LazyBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Tests hearing paragraph.
 */
class HelfiParagraphsHearings extends KernelTestBase {

  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'paragraphs',
    'external_entities',
    'helfi_platform_config',
    'config_rewrite',
    'helfi_api_base',
    'field',
    'file',
    'imagecache_external',
    'responsive_image',
    'text',
    'helfi_paragraphs_hearings',
    'system',
    'link',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Triggers rebuilding routes.
    // https://www.drupal.org/project/external_entities/issues/3549828.
    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installEntitySchema('paragraph');
    $this->installConfig(['helfi_paragraphs_hearings']);
  }

  /**
   * Test hearing paragraph.
   */
  public function testParagraph(): void {
    $paragraph = Paragraph::create([
      'type' => 'hearings',
    ]);
    $paragraph->save();

    $display = $this->prophesize(EntityViewDisplayInterface::class);
    $display->getComponent('list')->willReturn(['¯\_(ツ)_/¯']);

    $build = [];

    $hook = HearingsParagraphHooks::create($this->container);
    $hook->view($build, $paragraph, $display->reveal());

    $this->assertNotEmpty($build['list']['#lazy_builder'] ?? []);
  }

  /**
   * Tests lazy builder.
   */
  public function testLazyBuilder(): void {
    $client = $this->createMockHttpClient([
      // Empty response.
      new Response(body: json_encode([])),
      // Server refuses to brew coffee because it is, permanently, a teapot.
      new ClientException('I\'m a teapot', new Request('GET', ''), new Response(status: 418)),
      // Success.
      new Response(body: file_get_contents(dirname(__DIR__, 2) . '/fixtures/hearings.json')),
    ]);
    $this->container->set('http_client', $client);

    $sut = new LazyBuilder($this->container->get(EntityTypeManagerInterface::class));
    $hearings = ExternalEntityType::load('helfi_hearings');

    // Empty response.
    $build = $sut->lazyBuild();
    $this->assertEquals(60, $build['#cache']['max-age']);

    // Server failure.
    $build = $sut->lazyBuild();
    $this->assertEquals(60, $build['#cache']['max-age']);

    // Success.
    $build = $sut->lazyBuild();
    $this->assertEquals($hearings->getPersistentCacheMaxAge(), $build['#cache']['max-age']);
    $this->assertNotEmpty($build['list'] ?? []);
  }

}
