<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_csp\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\helfi_csp\CspLogService;
use Drupal\helfi_csp\HelfiCspServiceProvider;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Helfi CSP service provider.
 */
class HelfiCspServiceProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'csp',
    'csp_log',
    'helfi_csp',
    'system',
  ];

  /**
   * Tests that CspLogService is registered when csp_log module is enabled.
   */
  public function testCspLogServiceRegisteredWhenCspLogEnabled(): void {
    $this->assertTrue(
      $this->container->has(CspLogService::class),
      'CspLogService should be registered when csp_log is enabled.'
    );
    $this->assertInstanceOf(
      CspLogService::class,
      $this->container->get(CspLogService::class),
      'Container should return a CspLogService instance.'
    );
  }

  /**
   * Tests that CspLogService is not registered when csp_log module is absent.
   */
  public function testCspLogServiceNotRegisteredWhenCspLogDisabled(): void {
    $container = new ContainerBuilder();
    $container->setParameter('container.modules', [
      'helfi_csp' => [
        'path' => 'modules/contrib/helfi_platform_config/modules/helfi_csp',
        'filename' => 'helfi_csp.info.yml',
      ],
      'system' => [
        'path' => 'core/modules/system',
        'filename' => 'system.info.yml',
      ],
    ]);

    $provider = new HelfiCspServiceProvider();
    $provider->register($container);

    $this->assertFalse(
      $container->has(CspLogService::class),
      'CspLogService should not be registered when csp_log module is not enabled.'
    );
  }

}
