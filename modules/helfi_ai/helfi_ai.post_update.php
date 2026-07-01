<?php

/**
 * @file
 * Post-update hooks for helfi_ai.
 */

declare(strict_types=1);

/**
 * Installs the AI tone check config on existing installs.
 *
 * Default config under config/install and config/rewrite is only applied on
 * module install, so existing sites that already have helfi_ai do not get the
 * new tone-check prompt, the CKEditor toolbar rewrites or the permission from a
 * version bump. The helfi config update helper re-installs the module's default
 * config (skipping anything already present), applies config_rewrite and
 * refreshes permissions.
 *
 * A named post-update (rather than a numbered helfi_ai_update_N) is used on
 * purpose: the SEO-title feature branch already ships helfi_ai_update_10001,
 * and a named post-update cannot collide with it regardless of merge order.
 */
function helfi_ai_post_update_install_tone_check_config(): void {
  \Drupal::service('helfi_platform_config.config_update_helper')
    ->update('helfi_ai');
}
