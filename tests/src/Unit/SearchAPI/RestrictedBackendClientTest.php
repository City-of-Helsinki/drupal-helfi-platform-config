<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\SearchAPI;

use Drupal\elasticsearch_connector\SearchAPI\BackendClient;
use Drupal\helfi_platform_config\SearchAPI\RestrictedBackendClient;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the shared-index backend client.
 */
#[Group('helfi_platform_config')]
final class RestrictedBackendClientTest extends UnitTestCase {

  /**
   * Document indexing and deletion stay on the connector client.
   */
  public function testDocumentOperationsStayOnParent(): void {
    $indexItems = new \ReflectionMethod(RestrictedBackendClient::class, 'indexItems');
    $deleteItems = new \ReflectionMethod(RestrictedBackendClient::class, 'deleteItems');

    $this->assertSame(BackendClient::class, $indexItems->getDeclaringClass()->getName());
    $this->assertSame(BackendClient::class, $deleteItems->getDeclaringClass()->getName());
  }

}
