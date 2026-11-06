<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Hooks;

use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests path alias changes when Search API is not enabled.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class PathAliasHooksWithoutSearchApiTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'path_alias',
    'system',
    'user',
  ];

  /**
   * Path aliases can be changed without Search API.
   */
  public function testPathAliasChanges(): void {
    $this->assertFalse($this->container->has('search_api.entity_datasource.tracking_manager'));
    $this->installEntitySchema('path_alias');

    $alias = PathAlias::create([
      'path' => '/user/login',
      'alias' => '/log-in',
    ]);
    $alias->save();
    $alias->setAlias('/sign-in')->save();
    $alias->delete();

    $this->assertNull(PathAlias::load($alias->id()));
  }

}
