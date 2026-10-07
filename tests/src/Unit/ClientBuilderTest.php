<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\helfi_api_base\Mock\MockClientFactory;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Vault\AuthorizationToken;
use Drupal\helfi_api_base\Vault\VaultManager;
use Drupal\helfi_platform_config\ClientBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the etusivu elastic client builder.
 */
#[Group('helfi_platform_config')]
class ClientBuilderTest extends UnitTestCase {

  use EnvironmentResolverTrait;

  /**
   * Tests that authorization header is sent when vault item exists.
   */
  public function testAuthorizationHeader() : void {
    $request = $this->sendRequest([
      new AuthorizationToken('etusivu_elastic', 'dGVzdDp0ZXN0'),
    ]);

    $this->assertEquals('Basic dGVzdDp0ZXN0', $request->getHeaderLine('Authorization'));
  }

  /**
   * Makes a request using a client.
   *
   * @param \Drupal\helfi_api_base\Vault\VaultItemInterface[] $vaultItems
   *   The vault items.
   *
   * @return \Psr\Http\Message\RequestInterface
   *   The request that was sent.
   */
  private function sendRequest(array $vaultItems = []) : RequestInterface {
    $mockHandler = new MockHandler([
      new Response(200, [
        'Content-Type' => 'application/json',
        'X-Elastic-Product' => 'Elasticsearch',
      ], '{}'),
    ]);

    $sut = new ClientBuilder(
      $this->getEnvironmentResolver(Project::ETUSIVU, EnvironmentEnum::Local),
      new VaultManager($vaultItems),
      new MockClientFactory(new Client(['handler' => HandlerStack::create($mockHandler)])),
    );
    $sut->create()->info();

    $request = $mockHandler->getLastRequest();
    $this->assertInstanceOf(RequestInterface::class, $request);

    return $request;
  }

}
