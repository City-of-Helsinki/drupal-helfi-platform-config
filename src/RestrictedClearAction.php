<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

/**
 * What to do when drush sapi-c targets a restricted search index.
 */
enum RestrictedClearAction {

  case Allow;
  case Block;
  case Confirm;

}
