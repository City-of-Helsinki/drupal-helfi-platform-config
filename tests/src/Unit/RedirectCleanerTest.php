<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
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
   * The config factory mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The entity type manager mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The logger mock object.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * The RedirectCleaner service under test.
   *
   * @var \Drupal\helfi_platform_config\RedirectCleaner
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
  private function setupConfigMock(bool $enabled = TRUE, int $range = 100): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->willReturnCallback(function ($key) use ($enabled, $range) {
        if ($key === 'enable') {
          return $enabled;
        }
        if ($key === 'range') {
          return $range;
        }
        return NULL;
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
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked entity storage interface.
   */
  private function setupStorageMock(array $redirects = [], array $loadMap = []): EntityStorageInterface {
    $storage = $this->createMock(EntityStorageInterface::class);

    $storage->method('getQuery')
      ->willReturn(new class {

        /**
         * A query mock object.
         */
        public function condition() {
          return $this;
        }

        /**
         * A query mock object.
         */
        public function accessCheck() {
          return $this;
        }

        /**
         * A query mock object.
         */
        public function range() {
          return $this;
        }

        /**
         * A query mock object.
         */
        public function execute() {
          return [];
        }

      });

    if (!empty($redirects)) {
      $storage->method('getQuery')
        ->willReturn(new class($redirects) {

          /**
           * The redirect IDs.
           *
           * @var array
           */
          public $redirects;

          /**
           * A query mock object.
           */
          public function __construct($redirects) {
            $this->redirects = $redirects;
          }

          /**
           * A query mock object.
           */
          public function condition() {
            return $this;
          }

          /**
           * A query mock object.
           */
          public function accessCheck() {
            return $this;
          }

          /**
           * A query mock object.
           */
          public function range() {
            return $this;
          }

          /**
           * A query mock object.
           */
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

        /**
         * Gets entity type key.
         */
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
   * This test confirms the feature enablement check.
   *
   * @covers ::isEnabled
   */
  public function testIsEnabledFeatureEnabled(): void {
    $this->setupConfigMock(TRUE);
    $this->assertTrue($this->cleaner->isEnabled());
  }

  /**
   * Tests isEnabled() method when the feature is disabled.
   *
   * Verifies that isEnabled() returns FALSE when the configuration indicates
   * the redirect cleanup feature is disabled.
   *
   * This test confirms the feature disablement check.
   *
   * @covers ::isEnabled
   */
  public function testIsEnabledFeatureDisabled(): void {
    $this->setupConfigMock(FALSE);
    $this->assertFalse($this->cleaner->isEnabled());
  }

  /**
   * Tests unpublishExpiredRedirects() when the feature is disabled.
   *
   * Verifies that the method exits early and does not interact with entity
   * storage when the redirect cleanup feature is disabled.
   *
   * This test validates behavior when the feature is not active.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsFeatureDisabled(): void {
    // Setup configuration mock to disable the redirect cleaner feature.
    $this->setupConfigMock(FALSE);
    // Ensure that entity storage is not accessed when the feature is disabled.
    $this->entityTypeManager->expects($this->never())
      ->method('getStorage');
    // Call the method to confirm it exits early due to the disabled feature.
    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() when there are no redirects to process.
   *
   * Verifies that the method handles an empty result set from the query
   * gracefully, without attempting to load or process any entities.
   *
   * This test confirms behavior with no data to process.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsNoRedirects(): void {
    // Enable the redirect cleaner feature in the configuration.
    $this->setupConfigMock(TRUE);
    // Mock the entity query to return an empty result set, simulating no
    // redirects.
    $query = $this->createMock('\Drupal\Core\Entity\Query\QueryInterface');
    $query->method('condition')
      ->willReturnSelf();
    $query->method('accessCheck')
      ->willReturnSelf();
    $query->method('range')
      ->willReturnSelf();
    $query->method('execute')
      ->willReturn([]);

    // Mock the entity storage to return the query mock.
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')
      ->willReturn($query);
    $storage->method('getEntityType')
      ->willReturn(new class {

        /**
         * Gets entity type key.
         */
        public function getKey($key) {
          return $key === 'published' ? 'status' : ($key === 'custom' ? 'is_custom' : '');
        }

      });

    // Setup entity type manager to return the mocked storage.
    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willReturn($storage);
    // Ensure no logging occurs since there are no redirects to process.
    $this->logger->expects($this->never())
      ->method('info');
    // Call the method to test handling of an empty redirect list.
    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() successful unpublishing of redirects.
   *
   * Verifies that the method correctly identifies expired redirects, unpublishes
   * them, saves the changes, and logs the action appropriately.
   *
   * This test validates successful unpublishing workflow.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsSuccess(): void {
    // Enable the redirect cleaner feature in the configuration.
    $this->setupConfigMock(TRUE);
    // Mock the entity query to return a single redirect ID.
    $query = $this->createMock('\Drupal\Core\Entity\Query\QueryInterface');
    $query->method('condition')
      ->willReturnSelf();
    $query->method('accessCheck')
      ->willReturnSelf();
    $query->method('range')
      ->willReturnSelf();
    $query->method('execute')
      ->willReturn(['1']);

    // Mock the redirect entity to simulate unpublishing and saving.
    $redirect = $this->createMock(PublishableRedirect::class);
    $redirect->method('id')
      ->willReturn('1');
    $redirect->expects($this->once())
      ->method('setUnpublished');
    $redirect->expects($this->once())
      ->method('save');

    // Mock the entity storage to return the query and redirect entity.
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')
      ->willReturn($query);
    $storage->method('load')
      ->with('1')
      ->willReturn($redirect);
    $storage->method('getEntityType')
      ->willReturn(new class {

        /**
         * Gets entity type key.
         */
        public function getKey($key) {
          return $key === 'published' ? 'status' : ($key === 'custom' ? 'is_custom' : '');
        }

      });

    // Setup entity type manager to return the mocked storage.
    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willReturn($storage);

    // Expect logging of the unpublishing action for the redirect.
    $this->logger->expects($this->once())
      ->method('info')
      ->with('Unpublishing redirect: %id', ['%id' => '1']);

    // Call the method to test successful unpublishing of a redirect.
    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() when a save operation fails with an exception.
   *
   * Verifies that the method throws an exception if saving a redirect after
   * unpublishing fails, ensuring errors are not silently ignored.
   *
   * This test confirms error handling during entity save operations.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsSaveException(): void {
    // Enable the redirect cleaner feature in the configuration.
    $this->setupConfigMock(TRUE);
    // Mock the entity query to return a single redirect ID.
    $query = $this->createMock('\Drupal\Core\Entity\Query\QueryInterface');
    $query->method('condition')
      ->willReturnSelf();
    $query->method('accessCheck')
      ->willReturnSelf();
    $query->method('range')
      ->willReturnSelf();
    $query->method('execute')
      ->willReturn(['1']);

    // Mock the redirect entity to simulate unpublishing and a save failure.
    $redirect = $this->createMock(PublishableRedirect::class);
    $redirect->method('id')
      ->willReturn('1');
    $redirect->expects($this->once())
      ->method('setUnpublished');
    $redirect->method('save')
      ->willThrowException(new \Exception('Save failed'));

    // Mock the entity storage to return the query and redirect entity.
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')
      ->willReturn($query);
    $storage->method('load')
      ->with('1')
      ->willReturn($redirect);
    $storage->method('getEntityType')
      ->willReturn(new class {

        /**
         * Gets entity type key.
         */
        public function getKey($key) {
          return $key === 'published' ? 'status' : ($key === 'custom' ? 'is_custom' : '');
        }

      });

    // Setup entity type manager to return the mocked storage.
    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willReturn($storage);

    // Expect logging of the unpublishing attempt before the exception.
    $this->logger->expects($this->once())
      ->method('info')
      ->with('Unpublishing redirect: %id', ['%id' => '1']);

    // Expect an exception to be thrown due to the save failure.
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Save failed');

    // Call the method to test behavior when saving a redirect fails.
    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() processing multiple redirects within the range limit.
   *
   * Verifies that the method can handle multiple redirects up to the configured
   * range limit, unpublishing each one, saving changes, and logging actions.
   *
   * This test validates batch processing within defined limits.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testProcessMultipleRedirectsWithinRange(): void {
    // Enable the redirect cleaner feature and set a range limit of 3.
    $this->setupConfigMock(TRUE, 3);

    // Mock redirect entities for IDs 1, 2, and 3 to simulate unpublishing and
    // saving.
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

    // Setup arrays for storage mock to return the redirects.
    $redirects = ['1' => $redirect1, '2' => $redirect2, '3' => $redirect3];
    $loadMap = [['1', $redirect1], ['2', $redirect2], ['3', $redirect3]];
    $this->setupStorageMock($redirects, $loadMap);

    // Expect logging for each processed redirect, up to the range limit of 3.
    $this->logger->expects($this->exactly(3))
      ->method('info')
      ->withConsecutive(
        ['Unpublishing redirect: %id', ['%id' => '1']],
        ['Unpublishing redirect: %id', ['%id' => '2']],
        ['Unpublishing redirect: %id', ['%id' => '3']]
      );

    // Call the method to test processing multiple redirects within the range.
    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() when InvalidPluginDefinitionException is thrown.
   *
   * Verifies that the method handles an InvalidPluginDefinitionException,
   * indicating a configuration issue with the redirect entity type, by exiting
   * early without logging or processing.
   *
   * This test confirms exception handling for plugin definition issues.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsInvalidPluginDefinitionException(): void {
    // Enable the redirect cleaner feature in the configuration.
    $this->setupConfigMock(TRUE);
    // Simulate an InvalidPluginDefinitionException when attempting to get storage.
    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willThrowException(new InvalidPluginDefinitionException('redirect'));

    // Ensure no logging occurs due to the exception.
    $this->logger->expects($this->never())
      ->method('info');

    // Call the method to test early exit due to the exception.
    $this->cleaner->unpublishExpiredRedirects();
  }

  /**
   * Tests unpublishExpiredRedirects() when the redirect module is not installed.
   *
   * Verifies that the method handles a PluginNotFoundException, indicating the
   * redirect module is not installed, by exiting early without logging or processing.
   *
   * This test confirms behavior when required modules are missing.
   *
   * @covers ::unpublishExpiredRedirects
   * @covers ::isEnabled
   */
  public function testUnpublishExpiredRedirectsRedirectModuleNotInstalled(): void {
    // Enable the redirect cleaner feature in the configuration.
    $this->setupConfigMock(TRUE);
    // Simulate a PluginNotFoundException when attempting to get storage.
    $this->entityTypeManager->method('getStorage')
      ->with('redirect')
      ->willThrowException(new PluginNotFoundException('redirect'));

    // Ensure no logging occurs due to the exception.
    $this->logger->expects($this->never())
      ->method('info');

    // Call the method to test early exit due to the missing module.
    $this->cleaner->unpublishExpiredRedirects();
  }

}
