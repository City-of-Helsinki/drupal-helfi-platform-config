<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_platform_config\Entity\PublishableRedirect;
use Drupal\helfi_platform_config\RedirectCleaner;
use Drupal\KernelTests\KernelTestBase;
use Drupal\path_alias\Entity\PathAlias;

/**
 * Tests custom redirect entity.
 *
 * @group helfi_platform_config
 */
class RedirectEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'link',
    'system',
    'redirect',
    'path_alias',
    'config_rewrite',
    'helfi_platform_config',
    'helfi_api_base',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('redirect');
  }

  /**
   * Tests publishable redirect.
   */
  public function testPublishableRedirect(): void {
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('redirect');

    $entityClass = $storage->getEntityClass();
    $reflection = new \ReflectionClass($entityClass);

    $this->assertTrue($reflection->implementsInterface(EntityPublishedInterface::class));

    $redirect = $storage->create();

    $this->assertInstanceOf(PublishableRedirect::class, $redirect);

    $redirect->setSource('/source');
    $redirect->setRedirect('/destination');
    $redirect->setStatusCode(300);
    $redirect->save();

    // Published by default.
    $this->assertTrue($redirect->isPublished());

    // Generated with API => should not be custom.
    $this->assertFalse($redirect->isCustom());

    $repository = $this->container->get('redirect.repository');

    $match = $repository->findMatchingRedirect('/source', language: $redirect->language()->getId());
    $this->assertNotEmpty($match);

    // Unpublishing redirect should remove it from findMatchingRedirect.
    $redirect->setUnpublished();
    $redirect->save();

    $match = $repository->findMatchingRedirect('/source', language: $redirect->language()->getId());
    $this->assertEmpty($match);
  }

  /**
   * Tests that auto redirect works.
   *
   * @see \redirect_path_alias_update()
   */
  public function testAutoRedirect(): void {
    $this->config('redirect.settings')
      ->set('auto_redirect', TRUE)
      ->save();

    $pathAlias = PathAlias::create([
      'path' => '/unaliased/path',
      'alias' => '/aliased/path/old',
    ]);
    $pathAlias->save();
    $pathAlias->setAlias('/aliased/path/new');
    $pathAlias->save();

    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('redirect');

    $redirects = $storage->loadByProperties([
      'enabled' => 1,
    ]);

    // One redirect should be created when path alias is updated.
    $this->assertNotEmpty($redirects);
  }

  /**
   * Tests redirect cleaner with unpublish action.
   */
  public function testRedirectCleanerUnpublishAction(): void {
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('redirect');

    $expireAfter = '-6 months';
    $expirationTimestamp = strtotime($expireAfter);
    $this->assertNotFalse($expirationTimestamp);

    $tests = [
      [
        'redirect_source' => 'source/unpublish/0',
        'enabled' => 1,
        'is_custom' => 1,
        'created' => strtotime('-1 year'),
      ],
      [
        'redirect_source' => 'source/unpublish/1',
        'enabled' => 1,
        'is_custom' => 0,
        'created' => strtotime('-1 year'),
      ],
      [
        'redirect_source' => 'source/unpublish/2',
        'enabled' => 1,
        'is_custom' => 0,
        'created' => strtotime('now'),
      ],
    ];

    foreach ($tests as $test) {
      $redirect = $storage->create([
        'redirect_source' => $test['redirect_source'],
        'redirect_redirect' => '/destination',
        'status_code' => 301,
        'enabled' => $test['enabled'],
        'is_custom' => $test['is_custom'],
        'created' => $test['created'],
      ]);
      $redirect->save();
    }

    // Enable the service + configure expiration + action.
    $this->config('helfi_platform_config.redirect_cleaner')
      ->set('enable', TRUE)
      ->set('expire_after', $expireAfter)
      ->set('action', RedirectCleaner::ACTION_UNPUBLISH)
      ->save();

    $cleaner = $this->container->get(RedirectCleaner::class);
    $cleaner->cleanExpiredRedirects();

    foreach ($tests as $test) {
      $redirects = $storage->loadByProperties(['redirect_source' => $test['redirect_source']]);
      $redirect = reset($redirects);

      $this->assertNotFalse($redirect);
      $this->assertInstanceOf(EntityPublishedInterface::class, $redirect);

      $shouldRemainPublished = $test['is_custom'] || $test['created'] > $expirationTimestamp;
      $this->assertEquals($shouldRemainPublished, $redirect->isPublished());
    }
  }

  /**
   * Tests redirect cleaner with delete action.
   */
  public function testRedirectCleanerDeleteAction(): void {
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('redirect');

    $expireAfter = '-6 months';
    $expirationTimestamp = strtotime($expireAfter);
    $this->assertNotFalse($expirationTimestamp);

    $tests = [
      [
        'redirect_source' => 'source/delete/0',
        'enabled' => 1,
        'is_custom' => 1,
        'created' => strtotime('-1 year'),
      ],
      [
        'redirect_source' => 'source/delete/1',
        'enabled' => 1,
        'is_custom' => 0,
        'created' => strtotime('-1 year'),
      ],
      [
        'redirect_source' => 'source/delete/2',
        'enabled' => 1,
        'is_custom' => 0,
        'created' => strtotime('now'),
      ],
    ];

    foreach ($tests as $test) {
      $redirect = $storage->create([
        'redirect_source' => $test['redirect_source'],
        'redirect_redirect' => '/destination',
        'status_code' => 301,
        'enabled' => $test['enabled'],
        'is_custom' => $test['is_custom'],
        'created' => $test['created'],
      ]);
      $redirect->save();
    }

    // Enable the service + configure expiration + action.
    $this->config('helfi_platform_config.redirect_cleaner')
      ->set('enable', TRUE)
      ->set('expire_after', $expireAfter)
      ->set('action', RedirectCleaner::ACTION_DELETE)
      ->save();

    $cleaner = $this->container->get(RedirectCleaner::class);
    $cleaner->cleanExpiredRedirects();

    foreach ($tests as $test) {
      $redirects = $storage->loadByProperties(['redirect_source' => $test['redirect_source']]);

      $shouldBeDeleted = !$test['is_custom'] && $test['created'] < $expirationTimestamp;
      if ($shouldBeDeleted) {
        $this->assertEmpty($redirects);
        continue;
      }

      $redirect = reset($redirects);
      $this->assertNotFalse($redirect);
      $this->assertInstanceOf(EntityPublishedInterface::class, $redirect);

      // Delete action should not unpublish the remaining ones.
      $this->assertTrue($redirect->isPublished());
    }
  }

}
