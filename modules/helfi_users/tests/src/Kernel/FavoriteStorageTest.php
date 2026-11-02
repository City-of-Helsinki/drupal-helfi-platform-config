<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\helfi_users\FavoriteStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests FavoriteStorage.
 */
#[CoversClass(FavoriteStorage::class)]
#[Group('helfi_users')]
class FavoriteStorageTest extends FavoriteKernelTestBase {

  /**
   * The favorite storage.
   *
   * @var \Drupal\helfi_users\FavoriteStorage
   */
  private FavoriteStorage $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->storage = $this->container->get(FavoriteStorage::class);
  }

  /**
   * Tests storing favorites.
   */
  public function testStoringFavorites(): void {
    $user = $this->createEditor();
    $other = $this->createEditor();
    $node = $this->createPage();
    $translation = $this->addFinnishTranslation($node);

    $cache = $this->container->get('cache.default');
    assert($cache instanceof CacheBackendInterface);
    $cache->set('user_favorites', TRUE, tags: ['helfi_favorite:' . $user->id()]);
    $cache->set('other_favorites', TRUE, tags: ['helfi_favorite:' . $other->id()]);

    $this->assertTrue($this->storage->toggle($user, $translation), 'The first toggle adds the favorite.');
    $this->assertTrue($this->storage->isFavorite($user, $translation), 'The Finnish translation is a favorite.');
    $this->assertFalse($this->storage->isFavorite($user, $node), 'The English translation is not a favorite.');
    $this->assertFalse($this->storage->isFavorite($other, $translation), 'The translation is not a favorite of another user.');
    $this->assertFalse($cache->get('user_favorites'), 'The user cache item is invalidated.');
    $this->assertNotFalse($cache->get('other_favorites'), 'Another user cache item is kept.');

    $rows = $this->container->get('database')
      ->select('helfi_users_favorite', 'f')
      ->fields('f', ['uid', 'entity_type', 'entity_id', 'langcode'])
      ->execute()
      ->fetchAll(FetchAs::Associative);
    $this->assertEquals([
      [
        'uid' => $user->id(),
        'entity_type' => 'node',
        'entity_id' => $node->id(),
        'langcode' => 'fi',
      ],
    ], $rows, 'The favorite is stored for the translation.');

    $this->assertFalse($this->storage->toggle($user, $translation), 'The second toggle removes the favorite.');
    $this->assertFalse($this->storage->isFavorite($user, $translation), 'The translation is not a favorite after removing.');

    $this->storage->add($user, $node);
    $this->storage->add($user, $node);
    $this->assertTrue($this->storage->isFavorite($user, $node), 'The page is a favorite after adding twice.');
    $this->assertTrue($this->storage->remove($user, $node), 'The first remove removes the favorite.');
    $this->assertFalse($this->storage->remove($user, $node), 'The second remove has nothing to remove.');
  }

}
