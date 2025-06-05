<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\ConfigUpdate;

use Drupal\Component\Uuid\Php;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the StorageComparer.
 *
 * The patch for the StorageComparer changes the handling of configuration
 * arrays when comparing configurations. This test ensures that the patch
 * works as expected and there should not be any false positives when only
 * configuration keys change order.
 *
 * @group helfi_platform_config
 */
class StorageComparerSortTest extends UnitTestCase {

  /**
   * @var \Drupal\Core\Config\StorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $sourceStorage;

  /**
   * @var \Drupal\Core\Config\StorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $targetStorage;

  /**
   * The storage comparer to test.
   *
   * @var \Drupal\Core\Config\StorageComparer
   */
  protected $storageComparer;

  /**
   * An array of test configuration data keyed by configuration name.
   *
   * @var array
   */
  protected $configData;

  /**
   * UUID.
   *
   * @var string
   */
  protected string $uuid;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->sourceStorage = $this->createMock('Drupal\Core\Config\StorageInterface');
    $this->targetStorage = $this->createMock('Drupal\Core\Config\StorageInterface');

    $this->sourceStorage->expects($this->atLeastOnce())
      ->method('getCollectionName')
      ->willReturn(StorageInterface::DEFAULT_COLLECTION);
    $this->targetStorage->expects($this->atLeastOnce())
      ->method('getCollectionName')
      ->willReturn(StorageInterface::DEFAULT_COLLECTION);

    $this->storageComparer = new StorageComparer($this->sourceStorage, $this->targetStorage);

    $uuid = new Php();
    $this->uuid = $uuid->generate();
  }

  /**
   * Returns test configuration data.
   *
   * @return array
   *   An array of test configuration data keyed by configuration name.
   */
  protected function getSourceData(): array {
    return [
      'helfi_platform_config.settings' => [
        'title' => 'Helfi Platform Config',
        'uuid' => $this->uuid,
        'unordered_alphabetical_variable' => FALSE,
        'alphabetical_variable' => TRUE,
      ],
    ];
  }

  /**
   * Returns test configuration data.
   *
   * @return array
   *   An array of test configuration data keyed by configuration name.
   */
  protected function getTargetData(): array {
    return [
      'helfi_platform_config.settings' => [
        'uuid' => $this->uuid,
        'title' => 'Helfi Platform Config',
        'alphabetical_variable' => TRUE,
        'unordered_alphabetical_variable' => FALSE,
      ],
    ];
  }

  /**
   * Tests the createChangelist() method with no actual changes.
   */
  public function testCreateChangelistUpdateWithoutChanges(): void {

    // Mock data using minimal data to use ConfigDependencyManger.
    $this->sourceStorage->expects($this->once())
      ->method('listAll')
      ->willReturn(array_keys($this->getSourceData()));
    $this->targetStorage->expects($this->once())
      ->method('listAll')
      ->willReturn(array_keys($this->getTargetData()));
    $this->sourceStorage->expects($this->once())
      ->method('readMultiple')
      ->willReturn($this->getSourceData());
    $this->targetStorage->expects($this->once())
      ->method('readMultiple')
      ->willReturn($this->getTargetData());
    $this->sourceStorage->expects($this->once())
      ->method('getAllCollectionNames')
      ->willReturn([]);
    $this->targetStorage->expects($this->once())
      ->method('getAllCollectionNames')
      ->willReturn([]);

    $this->storageComparer->createChangelist();

    // We expect the change list to be empty, because the configuration values
    // are the same just the order of keys are different.
    $this->assertEquals([], $this->storageComparer->getChangelist('update'));
    $this->assertEmpty($this->storageComparer->getChangelist('create'));
    $this->assertEmpty($this->storageComparer->getChangelist('delete'));
  }

  /**
   * Tests the createChangelist() method with changes.
   */
  public function testCreateChangelistUpdateWithChanges(): void {
    $source_data = $this->getSourceData();
    $target_data = $this->getTargetData();

    // Change a value to trigger a change in the config.
    $source_data['helfi_platform_config.settings']['title'] = 'New title';

    $this->sourceStorage->expects($this->once())
      ->method('listAll')
      ->willReturn(array_keys($source_data));
    $this->targetStorage->expects($this->once())
      ->method('listAll')
      ->willReturn(array_keys($target_data));
    $this->sourceStorage->expects($this->once())
      ->method('readMultiple')
      ->willReturn($source_data);
    $this->targetStorage->expects($this->once())
      ->method('readMultiple')
      ->willReturn($target_data);
    $this->sourceStorage->expects($this->once())
      ->method('getAllCollectionNames')
      ->willReturn([]);
    $this->targetStorage->expects($this->once())
      ->method('getAllCollectionNames')
      ->willReturn([]);

    $this->storageComparer->createChangelist();

    // We expect the change list to be updated, because the configuration
    // values have changed.
    $this->assertEquals(['helfi_platform_config.settings'], $this->storageComparer->getChangelist('update'));
    $this->assertEmpty($this->storageComparer->getChangelist('create'));
    $this->assertEmpty($this->storageComparer->getChangelist('delete'));
  }

}
