<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\helfi_users\FavoriteStorage;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests favorite cleanup on entity and user deletion.
 */
#[Group('helfi_users')]
class FavoriteCleanupTest extends FavoriteKernelTestBase {

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
    $this->installSchema('user', ['users_data']);
    $this->storage = $this->container->get(FavoriteStorage::class);
  }

  /**
   * Gets the stored favorites.
   *
   * @return string[]
   *   The user ID, entity ID and langcode of each favorite.
   */
  private function getFavorites(): array {
    $rows = $this->container->get('database')
      ->select('helfi_users_favorite', 'f')
      ->fields('f', ['uid', 'entity_id', 'langcode'])
      ->orderBy('uid')
      ->orderBy('entity_id')
      ->orderBy('langcode')
      ->execute();

    $favorites = [];
    foreach ($rows as $row) {
      $favorites[] = "$row->uid:$row->entity_id:$row->langcode";
    }
    return $favorites;
  }

  /**
   * Tests that deleting an entity removes its favorites from all users.
   */
  public function testEntityDeleteRemovesFavorites(): void {
    $user = $this->createEditor();
    $other = $this->createEditor();
    $node = $this->createPage();
    $translation = $this->addFinnishTranslation($node);
    $kept = $this->createPage();
    $this->storage->toggle($user, $node);
    $this->storage->toggle($user, $translation);
    $this->storage->toggle($other, $node);
    $this->storage->toggle($user, $kept);

    $node->delete();

    $this->assertEquals([$user->id() . ':' . $kept->id() . ':en'], $this->getFavorites(), 'Only the favorite of another page is kept.');
  }

  /**
   * Tests that deleting a translation removes only its favorites.
   */
  public function testTranslationDeleteRemovesOnlyTranslationFavorites(): void {
    $user = $this->createEditor();
    $node = $this->createPage();
    $translation = $this->addFinnishTranslation($node);
    $this->storage->toggle($user, $node);
    $this->storage->toggle($user, $translation);

    $node->removeTranslation('fi');
    $node->save();

    $this->assertEquals([$user->id() . ':' . $node->id() . ':en'], $this->getFavorites(), 'The English favorite is kept.');
  }

  /**
   * Tests that deleting a user removes only their favorites.
   */
  public function testUserDeleteRemovesUserFavorites(): void {
    $user = $this->createEditor();
    $other = $this->createEditor();
    $node = $this->createPage();
    $this->storage->toggle($user, $node);
    $this->storage->toggle($other, $node);

    $user->delete();

    $this->assertEquals([$other->id() . ':' . $node->id() . ':en'], $this->getFavorites(), 'The favorite of another user is kept.');
  }

  /**
   * Tests that deleting favorites clears the request cache.
   */
  public function testDeleteClearsRequestCache(): void {
    $user = $this->createEditor();
    $node = $this->createPage();
    $this->storage->toggle($user, $node);
    $this->assertTrue($this->storage->isFavorite($user, $node), 'The page is a favorite before deleting.');

    $this->storage->deleteByUser($user);

    $this->assertFalse($this->storage->isFavorite($user, $node), 'The page is not a favorite after deleting.');
  }

}
