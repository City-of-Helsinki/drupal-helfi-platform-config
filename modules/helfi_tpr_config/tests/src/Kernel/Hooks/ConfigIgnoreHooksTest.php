<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel;

use Drupal\config_ignore\ConfigIgnoreConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\helfi_tpr_config\Hook\ConfigIgnoreHooks;

/**
 * Tests config ignore hook alterations.
 *
 * @group helfi_tpr_config
 */
class ConfigIgnoreHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'config_ignore',
    'helfi_tpr_config',
  ];

  /**
   * Tests ignored config is altered for export operations.
   */
  public function testConfigIgnoreIgnoredAlter(): void {
    $ignored = new ConfigIgnoreConfig('advanced', [
      'create' => [
        'import' => [],
        'export' => ['existing.create'],
      ],
      'update' => [
        'import' => [],
        'export' => ['existing.update'],
      ],
      'delete' => [
        'import' => [],
        'export' => ['existing.delete'],
      ],
    ]);

    $hook = new ConfigIgnoreHooks();
    $hook->configIgnoreIgnoredAlter($ignored);

    $this->assertSame([
      'content.lock.settings:form_op_lock',
      'content.lock.settings:types',
      'existing.create',
    ], $ignored->getList('export', 'create'));

    $this->assertSame([
      'content.lock.settings:form_op_lock',
      'content.lock.settings:types',
      'existing.update',
    ], $ignored->getList('export', 'update'));

    $this->assertSame([
      'content.lock.settings:form_op_lock',
      'content.lock.settings:types',
      'existing.delete',
    ], $ignored->getList('export', 'delete'));

    $this->assertSame([], $ignored->getList('import', 'create'));
    $this->assertSame([], $ignored->getList('import', 'update'));
    $this->assertSame([], $ignored->getList('import', 'delete'));
  }

}
