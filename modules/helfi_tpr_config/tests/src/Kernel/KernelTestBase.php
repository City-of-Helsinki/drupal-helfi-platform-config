<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel;

use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase as PlatformConfigKernelTestBase;

/**
 * Base class for helfi_tpr_config kernel tests.
 */
abstract class KernelTestBase extends PlatformConfigKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'link',
    'user',
    'file',
    'media',
    'image',
    'address',
    'menu_link_content',
    'telephone',
    'metatag',
    'metatag_open_graph',
    'metatag_twitter_cards',
    'entity_reference_revisions',
    'paragraphs',
    'paragraphs_library',
    'options',
    'token',
    'helfi_tpr',
    'helfi_tpr_config',
  ];

}
