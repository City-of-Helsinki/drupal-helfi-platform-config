<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\helfi_api_base\Mock\MockClientFactory;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\helfi_api_base\Environment\ActiveProjectRoles;
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
    $this->assertEquals('helfi-etusivu-elastic', $request->getUri()->getHost());
  }

  /**
   * Tests that projects without elastic access use the proxy.
   */
  public function testElasticProxyFallback() : void {
    $request = $this->sendRequest([
      new AuthorizationToken('etusivu_elastic', 'dGVzdDp0ZXN0'),
    ], Project::GRANTS, EnvironmentEnum::Test);

    $this->assertFalse($request->hasHeader('Authorization'));
    $this->assertEquals('helfi-etusivu-elastic-proxy.test.hel.ninja', $request->getUri()->getHost());
  }

  /**
   * Makes a request using a client.
   *
   * @param \Drupal\helfi_api_base\Vault\VaultItemInterface[] $vaultItems
   *   The vault items.
   * @param string $project
   *   The active project.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentEnum $environment
   *   The active environment.
   *
   * @return \Psr\Http\Message\RequestInterface
   *   The request that was sent.
   */
  private function sendRequest(
    array $vaultItems = [],
    string $project = Project::ETUSIVU,
    EnvironmentEnum $environment = EnvironmentEnum::Local,
  ) : RequestInterface {
    $mockHandler = new MockHandler([
      new Response(200, [
        'Content-Type' => 'application/json',
        'X-Elastic-Product' => 'Elasticsearch',
      ], '{}'),
    ]);

    $environmentResolver = $this->getEnvironmentResolver($project, $environment);
    $sut = new ClientBuilder(
      $environmentResolver,
      new VaultManager($vaultItems),
      new MockClientFactory(new Client(['handler' => HandlerStack::create($mockHandler)])),
      new ActiveProjectRoles($environmentResolver),
    );
    $sut->create()->info();

    $request = $mockHandler->getLastRequest();
    $this->assertInstanceOf(RequestInterface::class, $request);

    return $request;
  }

}
