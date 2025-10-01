<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_etusivu_entities\Kernel\Plugin\Block;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Base class for block plugin tests.
 */
abstract class BlockTestBase extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_api_base',
    'helfi_platform_config',
    'node',
    'link',
    'language',
    'allowed_formats',
    'select2',
    'content_translation',
    'text',
    'options',
    'menu_ui',
    'scheduler',
    'config_rewrite',
    'external_entities',
    'helfi_etusivu_entities',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(array $modules = []): void {
    parent::setUp();

    // Triggers rebuilding routes.
    // https://www.drupal.org/project/external_entities/issues/3549828.
    $this->container
      ->get(RouteProviderInterface::class)
      ->getAllRoutes();

    $this->installEntitySchema('node');
    $this->installConfig(array_merge([
      'node',
      'helfi_etusivu_entities',
    ], $modules));
  }

}
