<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_\Unit;

use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_paragraphs_news_list\ClientBuilder;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use Elasticsearch\Client;

/**
 * Tests elastic client builder.
 *
 * @group helfi_api_base
 */
class ClientBuilderTest extends UnitTestCase {

  use EnvironmentResolverTrait;

  /**
   * Tests that client builder fallbacks to testing environment.
   */
  public function testEnvironmentFallback() : void {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $this->getConfigFactoryStub([
      'helfi_api_base.environment_resolver.settings' => [
        EnvironmentResolver::ENVIRONMENT_NAME_KEY => 'nonexistent',
      ],
    ]);
    $envResolver = new EnvironmentResolver($configFactory);
    $sut = new ClientBuilder($envResolver);
    $this->assertInstanceOf(Client::class, $sut->create());
  }

}
