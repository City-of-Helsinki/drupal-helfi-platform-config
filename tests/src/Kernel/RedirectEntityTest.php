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
   * Tests redirect cleaner.
   */
  public function testRedirectCleaner(): void {
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('redirect');

    $tests = [
      [
        'enabled' => 1,
        'is_custom' => 1,
        'created' => strtotime('-1 year'),
      ],
      [
        'enabled' => 1,
        'is_custom' => 0,
        'created' => strtotime('-1 year'),
      ],
      [
        'enabled' => 1,
        'is_custom' => 0,
        'created' => strtotime('now'),
      ],
    ];

    foreach ($tests as $id => $test) {
      $redirect = $storage->create([
        'redirect_source' => "source/$id",
        'redirect_redirect' => '/destination',
        'status_code' => 301,
      ] + $test);

      $redirect->save();
    }

    // Enable the service.
    $this->config('helfi_platform_config.redirect_cleaner')->set('enable', TRUE)->save();

    $cleaner = $this->container->get(RedirectCleaner::class);
    $cleaner->unpublishExpiredRedirects();

    foreach ($tests as $id => $test) {
      $redirects = $storage->loadByProperties(['redirect_source' => "source/$id"]);
      $redirect = reset($redirects);

      $this->assertInstanceOf(EntityPublishedInterface::class, $redirect);

      $this->assertEquals(
        $test['is_custom'] || $test['created'] > strtotime('-6 months'),
        $redirect->isPublished()
      );
    }
  }

}
