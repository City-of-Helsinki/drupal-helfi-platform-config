<?php

declare(strict_types=1);

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\external_entities\Entity\ExternalEntityType;
use Drupal\helfi_paragraphs_hearings\Hook\HearingsParagraphHooks;
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
  public function testParagraph() {
    $client = $this->createMockHttpClient([
      // Success.
      new Response(body: file_get_contents(dirname(__DIR__, 2) . '/fixtures/hearings.json')),
      // Empty response.
      new Response(body: json_encode([])),
      // Server refuses to brew coffee because it is, permanently, a teapot.
      new ClientException('I\'m a teapot', new Request('GET', ''), new Response(status: 418)),
    ]);
    $this->container->set('http_client', $client);

    $paragraph = Paragraph::create([
      'type' => 'hearings',
    ]);

    $build = [];

    $display = $this->prophesize(EntityViewDisplayInterface::class);
    $display->getComponent('list')->willReturn(['¯\_(ツ)_/¯']);

    $sut = HearingsParagraphHooks::create($this->container);

    $hearings = ExternalEntityType::load('helfi_hearings');

    // Success.
    $sut->view($build, $paragraph, $display->reveal(), 'default');
    $this->assertEquals($hearings->getPersistentCacheMaxAge(), $build['#cache']['max-age']);
    $this->assertNotEmpty($build['list'] ?? []);

    // Empty response.
    $sut->view($build, $paragraph, $display->reveal(), 'default');
    $this->assertEquals(60, $build['#cache']['max-age']);

    // Server failure.
    $sut->view($build, $paragraph, $display->reveal(), 'default');
    $this->assertEquals(60, $build['#cache']['max-age']);
  }

}
