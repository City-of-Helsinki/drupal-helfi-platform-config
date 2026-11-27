<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_entity_task\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\helfi_entity_task\EntityTaskStorage;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the entity task storage.
 */
#[Group('helfi_entity_task')]
#[CoversClass(EntityTaskStorage::class)]
#[RunTestsInSeparateProcesses]
class EntityTaskStorageTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_entity_task',
  ];

  /**
   * Tasks are marked, completed and invalidate the entity's cache tags.
   */
  public function testTaskStatus(): void {
    $this->installSchema('helfi_entity_task', ['helfi_entity_task']);
    $storage = $this->container->get(EntityTaskStorage::class);
    $checksum = $this->container->get('cache_tags.invalidator.checksum');
    $entity = EntityTest::create(['name' => 'Test']);
    $entity->save();
    $tags = $storage->getCacheTags($entity);
    $this->assertSame(['helfi_entity_task:entity_test:' . $entity->id()], $tags);

    $this->assertFalse($storage->isPending($entity, 'task'));
    $before = $checksum->getCurrentChecksum($tags);
    $storage->mark($entity, 'task');
    $this->assertTrue($storage->isPending($entity, 'task'));
    $this->assertFalse($storage->isPending($entity, 'other'));
    $this->assertNotSame($before, $checksum->getCurrentChecksum($tags));

    $time = $this->container->get('datetime.time')->getRequestTime();
    $storage->complete($entity, 'task');
    $this->assertFalse($storage->isPending($entity, 'task'));
    $this->assertTrue($storage->isCompletedSince($entity, 'task', $time));
    $this->assertFalse($storage->isCompletedSince($entity, 'task', $time + 1));
    $this->assertFalse($storage->isCompletedSince($entity, 'other', $time));
  }

}
