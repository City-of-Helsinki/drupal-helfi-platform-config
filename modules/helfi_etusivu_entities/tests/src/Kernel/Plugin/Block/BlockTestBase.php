<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel\Plugin\Block;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Base class for block plugin tests.
 */
abstract class BlockTestBase extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'diff',
    'path_alias',
    'link',
    'language',
    'options',
    'menu_ui',
    'content_translation',
    'select2',
    'field_group',
    'scheduler',
    'publication_date',
    'hdbt_admin_tools',
    'external_entities',
    'config_rewrite',
    'helfi_api_base',
    'helfi_platform_config',
    'helfi_node_announcement',
    'helfi_etusivu_entities',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Config installation generates URLs, which reads the alias storage.
    $this->installEntitySchema('path_alias');
    $this->installConfig([
      'node',
      'helfi_platform_config',
      'helfi_etusivu_entities',
      'helfi_node_announcement',
    ]);
  }

}
