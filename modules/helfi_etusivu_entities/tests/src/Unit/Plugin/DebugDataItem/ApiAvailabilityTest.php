<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Unit\Plugin\DebugData;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_etusivu_entities\Plugin\DebugDataItem\Announcement;
use Drupal\helfi_etusivu_entities\Plugin\DebugDataItem\Survey;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\helfi_etusivu_entities\Plugin\DebugDataItem\ApiAvailabilityBase
 * @group helfi_etusivu_entities
 */
class ApiAvailabilityTest extends UnitTestCase {

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
      ->shouldBeCalledTimes(2)
      ->willThrow(new \InvalidArgumentException());
    $container = new ContainerBuilder();
    $container->set(ClientInterface::class, $client->reveal());
    $container->set(EnvironmentResolverInterface::class, $environmentResolver->reveal());

    $this->assertFalse(Survey::create($container, [], '', [])->check());
    $this->assertFalse(Announcement::create($container, [], '', [])->check());
  }

  /**
   * Make sure check() fails on request failure.
   */
  public function testGuzzleException(): void {
    $client = $this->prophesize(ClientInterface::class);
    $client->request('GET', Argument::any())
      ->shouldBeCalledTimes(2)
      ->willThrow(new TransferException());
    $container = new ContainerBuilder();
    $container->set(ClientInterface::class, $client->reveal());
    $container->set(EnvironmentResolverInterface::class, $this->getEnvironmentResolver(Project::ASUMINEN, EnvironmentEnum::Local));

    $this->assertFalse(Survey::create($container, [], '', [])->check());
    $this->assertFalse(Announcement::create($container, [], '', [])->check());
  }

  /**
   * Test successful check().
   */
  public function testCheck(): void {
    $container = new ContainerBuilder();
    $container->set(ClientInterface::class, $this->createMockHttpClient([
      new Response(body: json_encode([
        'meta' => ['count' => 1],
      ])),
      new Response(body: json_encode([
        'meta' => ['count' => 1],
      ])),
    ]));
    $container->set(EnvironmentResolverInterface::class, $this->getEnvironmentResolver(Project::ASUMINEN, EnvironmentEnum::Local));

    $this->assertTrue(Survey::create($container, [], '', [])->check());
    $this->assertTrue(Announcement::create($container, [], '', [])->check());
  }

}
