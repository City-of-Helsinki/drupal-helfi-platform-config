<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\ConfigUpdate;

use Drupal\config_rewrite\ConfigRewriter as ConfigRewriterBase;

/**
 * A service decorating config rewriter.
 */
final class ConfigRewriter extends ConfigRewriterBase {

  /**
   * {@inheritdoc}
   */
  public function rewriteConfig(
    $original_config,
    $rewrite,
    $config_name,
    $extensionName
  ) : array {
    $rewritten_config = parent::rewriteConfig($original_config, $rewrite, $config_name, $extensionName);

    if (!isset($rewrite['config_rewrite'])) {
      $rewritten_config = ConfigMergeHelper::mergeDeepArray([
        $original_config,
        $rewrite,
      ]);
    }
    return $rewritten_config;
  }

}
