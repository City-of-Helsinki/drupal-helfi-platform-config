<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_platform_config\Entity\PublishableRedirect;
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

    $redirect->setSource('internal:/source');
    $redirect->setRedirect('internal:/destination');
    $redirect->setStatusCode(300);
    $redirect->save();

    // Published by default.
    $this->assertTrue($redirect->isPublished());
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
      'status' => 1,
    ]);

    // Redirect should be created when path alias is updated.
    $this->assertNotEmpty($redirects);
  }

}