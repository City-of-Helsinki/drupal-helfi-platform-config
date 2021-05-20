<?php

/**
 * @file
 * Contains deploy functions for HELfi platform config.
 */

/**
 * Revoke Article content type permissions.
 */
function helfi_platform_config_deploy_9001_permissions() {
  user_role_revoke_permissions('admin', [
    'create article content',
    'delete any article content',
    'delete article revisions',
    'delete own article content',
    'edit any article content',
    'edit own article content',
    'revert article revisions',
    'translate article node',
    'view article revisions',
  ]);
  user_role_revoke_permissions('authenticated', [
    'create article content',
    'delete own article content',
    'edit own article content',
    'revert article revisions',
    'view article revisions',
  ]);
  return t('Successfully revoked Article permissions.');
}
