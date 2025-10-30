<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Unit\Plugin\DebugData;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_paragraphs_news_list\Plugin\DebugDataItem\NewsApiAvailability;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\helfi_paragraphs_news_list\Plugin\DebugDataItem\NewsApiAvailability
 * @group helfi_paragraphs_news_list
 */
class NewsApiAvailabilityTest extends UnitTestCase {

  use ProphecyTrait;
  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * Make sure check() fails if environment resolver fails.
   */
  public function testEnvironmentResolverException(): void {
    $client = $this->prophesize(ClientInterface::class);
    $environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $environmentResolver->getActiveEnvironmentName()
      ->shouldBeCalled()
      ->willThrow(new \InvalidArgumentException());
    $container = new ContainerBuilder();
    $container->set(ClientInterface::class, $client->reveal());
    $container->set(EnvironmentResolverInterface::class, $environmentResolver->reveal());

    $this->assertFalse(NewsApiAvailability::create($container, [], '', [])->check());
  }

  /**
   * Make sure check() fails on request failure.
   */
  public function testGuzzleException(): void {
    $client = $this->prophesize(ClientInterface::class);
    $client->request('GET', Argument::any())
      ->shouldBeCalled()
      ->willThrow(new TransferException());
    $container = new ContainerBuilder();
    $container->set(ClientInterface::class, $client->reveal());
    $container->set(EnvironmentResolverInterface::class, $this->getEnvironmentResolver(Project::ASUMINEN, EnvironmentEnum::Local));

    $this->assertFalse(NewsApiAvailability::create($container, [], '', [])->check());
  }

  /**
   * Test successful check().
   */
  public function testCheck(): void {
    $container = new ContainerBuilder();
    $container->set(ClientInterface::class, $this->createMockHttpClient([
      new Response(body: json_encode([
        'version' => [
          'value' => '1',
        ],
      ])),
    ]));
    $container->set(EnvironmentResolverInterface::class, $this->getEnvironmentResolver(Project::ASUMINEN, EnvironmentEnum::Local));

    $this->assertTrue(NewsApiAvailability::create($container, [], '', [])->check());
  }

}
