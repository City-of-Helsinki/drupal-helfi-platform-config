<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\helfi_platform_config\RedirectCleaner;
use Drupal\helfi_platform_config\Entity\PublishableRedirect;
use Psr\Log\LoggerInterface;

/**
 * Tests the RedirectCleaner class for unpublishing expired redirects.
 *
 * This test suite verifies the functionality of the RedirectCleaner service,
 * ensuring it correctly handles various scenarios including feature toggling,
 * entity storage interactions, exception handling, and processing multiple
 * redirects within configured limits.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\RedirectCleaner
 * @group helfi_platform_config
 */
class RedirectCleanerTest extends UnitTestCase {

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject Config factory mock.
   */
  protected $configFactory;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject Entity type manager mock.
   */
  protected $entityTypeManager;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject Logger mock.
   */
  protected $logger;

  /**
   * @var \Drupal\helfi_platform_config\RedirectCleaner The cleaner service under test.
   */
  protected $cleaner;

  /**
   * {@inheritdoc}
   *
   * Sets up the test environment by initializing mock objects for dependencies
   * and creating an instance of the RedirectCleaner service.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->cleaner = new RedirectCleaner(
      $this->configFactory,
      $this->entityTypeManager,
      $this->logger
    );
  }

  /**
   * Helper method to set up configuration mock for RedirectCleaner.
   *
   * Configures the mock to return specified values for feature enablement and
   * range limit, simulating different configuration scenarios.
   *
   * @param bool $enabled
   *   Whether the redirect cleanup feature is enabled.
   * @param int $range
   *   The maximum number of redirects to process in a single run.
   */
  private function setupConfigMock(bool $enabled = true, int $range = 100): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnCallback(function ($key) use ($enabled, $range) {
        if ($key === 'enable') {
          return $enabled;
        }
        if ($key === 'range') {
          return $range;
        }
        return null;
      });
    $this->configFactory->method('get')
      ->with('helfi_platform_config.redirect_cleaner')
      ->willReturn($config);
  }

  /**
   * Helper method to set up entity storage mock for redirect entities.
   *
   * Prepares a mock storage to simulate querying and loading redirect entities,
   * allowing tests to control the data returned and test various entity states.
   *
   * @param array $redirects
   *   Array of redirect entities to return from query execution.
   * @param array $loadMap
   *   Array mapping redirect IDs to entities for the load() method.
   * @return \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked entity storage interface.
   */
  private function setupStorageMock(array $redirects = [], array $loadMap = []): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    
    $storage->method('getQuery')
      ->willReturn(new class {
        public function condition() {
          return $this;
        }
        public function accessCheck() {
          return $this;
        }
        public function range() {
          return $this;
        }
        public function execute() {
          return [];
        }
      });
    
    if (!empty($redirects)) {
      $storage->method('getQuery')
        ->willReturn(new class($redirects) {
          private $redirects;
          public function __construct($redirects) {
            $this->redirects = $redirects;
          }
          public function condition() {
            return $this;
          }
          public function accessCheck() {
            return $this;
          }
          public function range() {
            return $this;
          }
          public function execute() {
            return array_keys($this->redirects);
          }
        });
    }
    
    if (!empty($loadMap)) {
      $storage->method('load')
        ->willReturnMap($loadMap);
    }
    
    $storage->method('getEntityType')
      ->willReturn(new class {
        public function getKey($key) {
          return $key === 'published' ? 'status' : ($key === 'custom' ? 'is_custom' : '');
        }
      });
    
    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willReturn($storage);
    
    return $storage;
  }

  /**
   * Tests isEnabled() method when the feature is enabled.
   *
   * Verifies that isEnabled() returns TRUE when the configuration indicates
   * the redirect cleanup feature is enabled.
   *
   * @covers ::isEnabled
   */
  public function testIsEnabledFeatureEnabled(): void {
    $this->setupConfigMock(true);
    $this->assertTrue($this->cleaner->isEnabled());
  }

  /**
   * Tests isEnabled() method when the feature is disabled.
   *
   * Verifies that isEnabled() returns FALSE when the configuration indicates
   * the redirect cleanup feature is disabled.
   *
   * @covers ::isEnabled
   */
  public function testIsEnabledFeatureDisabled(): void {
    $this->setupConfigMock(false);
    $this->assertFalse($this->cleaner->isEnabled());
  }

  /**
   * Tests unpublishExpiredRedirects() when the feature is disabled.
   *
   * Verifies that the method exits early and does not interact with entity
   * storage when the redirect cleanup feature is disabled.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsFeatureDisabled(): void {
    $this->setupConfigMock(false);
    $this->entityTypeManager->expects($this->never())
      ->method('getStorage');

    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() when there are no redirects to process.
   *
   * Verifies that the method handles an empty result set from the query
   * gracefully, without attempting to load or process any entities.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsNoRedirects(): void {
    $this->setupConfigMock(true);
    $query = $this->createMock('\Drupal\Core\Entity\Query\QueryInterface');
    $query->method('condition')
      ->willReturnSelf();
    $query->method('accessCheck')
      ->willReturnSelf();
    $query->method('range')
      ->willReturnSelf();
    $query->method('execute')
      ->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')
      ->willReturn($query);
    $storage->method('getEntityType')
      ->willReturn(new class {
        public function getKey($key) {
          return $key === 'published' ? 'status' : ($key === 'custom' ? 'is_custom' : '');
        }
      });

    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willReturn($storage);

    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() successful unpublishing of redirects.
   *
   * Verifies that the method correctly identifies expired redirects, unpublishes
   * them, saves the changes, and logs the action appropriately.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsSuccess(): void {
    $this->setupConfigMock(true);
    $query = $this->createMock('\Drupal\Core\Entity\Query\QueryInterface');
    $query->method('condition')
      ->willReturnSelf();
    $query->method('accessCheck')
      ->willReturnSelf();
    $query->method('range')
      ->willReturnSelf();
    $query->method('execute')
      ->willReturn(['1']);

    $redirect = $this->createMock(PublishableRedirect::class);
    $redirect->method('id')
      ->willReturn('1');
    $redirect->expects($this->once())
      ->method('setUnpublished');
    $redirect->expects($this->once())
      ->method('save');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')
      ->willReturn($query);
    $storage->method('load')
      ->with('1')
      ->willReturn($redirect);
    $storage->method('getEntityType')
      ->willReturn(new class {
        public function getKey($key) {
          return $key === 'published' ? 'status' : ($key === 'custom' ? 'is_custom' : '');
        }
      });

    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willReturn($storage);

    $this->logger->expects($this->once())
      ->method('info')
      ->with('Unpublishing redirect: %id', ['%id' => '1']);

    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() when a save operation fails with an exception.
   *
   * Verifies that the method throws an exception if saving a redirect after
   * unpublishing fails, ensuring errors are not silently ignored.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsSaveException(): void {
    $this->setupConfigMock(true);
    $query = $this->createMock('\Drupal\Core\Entity\Query\QueryInterface');
    $query->method('condition')
      ->willReturnSelf();
    $query->method('accessCheck')
      ->willReturnSelf();
    $query->method('range')
      ->willReturnSelf();
    $query->method('execute')
      ->willReturn(['1']);

    $redirect = $this->createMock(PublishableRedirect::class);
    $redirect->method('id')
      ->willReturn('1');
    $redirect->expects($this->once())
      ->method('setUnpublished');
    $redirect->method('save')
      ->willThrowException(new \Exception('Save failed'));

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')
      ->willReturn($query);
    $storage->method('load')
      ->with('1')
      ->willReturn($redirect);
    $storage->method('getEntityType')
      ->willReturn(new class {
        public function getKey($key) {
          return $key === 'published' ? 'status' : ($key === 'custom' ? 'is_custom' : '');
        }
      });

    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willReturn($storage);

    $this->logger->expects($this->once())
      ->method('info')
      ->with('Unpublishing redirect: %id', ['%id' => '1']);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Save failed');

    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() processing multiple redirects within the range limit.
   *
   * Verifies that the method can handle multiple redirects up to the configured
   * range limit, unpublishing each one, saving changes, and logging actions.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testProcessMultipleRedirectsWithinRange(): void {
    $this->setupConfigMock(true, 3);
    
    $redirect1 = $this->createMock(PublishableRedirect::class);
    $redirect1->method('id')
      ->willReturn('1');
    $redirect1->expects($this->once())
      ->method('setUnpublished');
    $redirect1->expects($this->once())
      ->method('save');

    $redirect2 = $this->createMock(PublishableRedirect::class);
    $redirect2->method('id')
      ->willReturn('2');
    $redirect2->expects($this->once())
      ->method('setUnpublished');
    $redirect2->expects($this->once())
      ->method('save');

    $redirect3 = $this->createMock(PublishableRedirect::class);
    $redirect3->method('id')
      ->willReturn('3');
    $redirect3->expects($this->once())
      ->method('setUnpublished');
    $redirect3->expects($this->once())
      ->method('save');

    $redirects = ['1' => $redirect1, '2' => $redirect2, '3' => $redirect3];
    $loadMap = [['1', $redirect1], ['2', $redirect2], ['3', $redirect3]];
    $this->setupStorageMock($redirects, $loadMap);
    
    $this->logger->expects($this->exactly(3))
      ->method('info')
      ->withConsecutive(
        ['Unpublishing redirect: %id', ['%id' => '1']],
        ['Unpublishing redirect: %id', ['%id' => '2']],
        ['Unpublishing redirect: %id', ['%id' => '3']]
      );
    
    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() when InvalidPluginDefinitionException is thrown.
   *
   * Verifies that the method handles an InvalidPluginDefinitionException,
   * indicating a configuration issue with the redirect entity type, by exiting
   * early without logging or processing.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsInvalidPluginDefinitionException(): void {
    $this->setupConfigMock(true);
    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willThrowException(new \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException('redirect'));

    $this->logger->expects($this->never())
      ->method('info');

    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() when the redirect module is not installed.
   *
   * Verifies that the method handles a PluginNotFoundException for the redirect
   * entity type, indicating the module is not installed, by exiting early without
   * logging or processing.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsRedirectModuleNotInstalled(): void {
    $this->setupConfigMock(true);
    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willThrowException(new \Drupal\Component\Plugin\Exception\PluginNotFoundException('redirect'));

    $this->logger->expects($this->never())
      ->method('info');

    $this->cleaner->unpublishExpiredRedirects();
  }
}
