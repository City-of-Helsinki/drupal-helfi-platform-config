<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigRewriter;
use Drupal\helfi_platform_config\HelfiPlatformConfigServiceProvider;
use Drupal\helfi_platform_config\SearchAPI\Query\QueryResultParser;
use Drupal\helfi_platform_config\SearchAPI\RestrictedBackendClientFactory;
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
    $this->assertInstanceOf(RestrictedBackendClientFactory::class, $this->container->get('elasticsearch_connector.backend_client_factory'));
  }

  /**
   * Tests the default image parameter.
   */
  public function testDefaultImageParameter() : void {
    $module_path = $this->container->get(ModuleExtensionList::class)->getPath('helfi_platform_config');

    $this->assertSame([
      'default' => "$module_path/fixtures/og-global.png",
      'sv' => "$module_path/fixtures/og-global-sv.png",
    ], $this->container->getParameter('helfi_platform_config.default_image'));

    $this->assertSame(
      $this->container->get(FileUrlGeneratorInterface::class)->generateAbsoluteString("$module_path/fixtures/og-global.png"),
      $this->container->get('helfi_platform_config.og_image.default')->buildUri(NULL),
    );
  }

}
