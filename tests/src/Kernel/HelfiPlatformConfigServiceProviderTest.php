<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\helfi_platform_config\ConfigUpdate\ConfigRewriter;
use Drupal\helfi_platform_config\HelfiPlatformConfigServiceProvider;
use Drupal\helfi_platform_config\SearchAPI\Query\QueryResultParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Helfi platform config service provider.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class HelfiPlatformConfigServiceProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'elasticsearch_connector',
    'search_api',
  ];

  /**
   * Tests service definition altering.
   */
  public function testServiceDefinitionAlter() {
    $serviceProvider = new HelfiPlatformConfigServiceProvider();
    $serviceProvider->alter($this->container);
    $this->assertInstanceOf(ConfigRewriter::class, $this->container->get('config_rewrite.config_rewriter'));
    $this->assertInstanceOf(QueryResultParser::class, $this->container->get('elasticsearch_connector.query_result_parser'));
  }

}
