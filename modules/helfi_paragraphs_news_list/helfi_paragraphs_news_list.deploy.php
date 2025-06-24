<?php

/**
 * @file
 * Contains news list paragraph deploy hooks.
 */

/**
 * Migrate the field_limit values to field_news_limit in deploy hook.
 */
function helfi_paragraphs_news_list_deploy_news_list_limit_migration(): void {
  $old_rows = \Drupal::database()
    ->select('paragraph__field_limit')
    ->fields('paragraph__field_limit')
    ->condition('bundle', 'news_list')
    ->execute()
    ->fetchAll();

  if (!$old_rows) {
    return;
  }

  // Reapply the values, update the values to match the select list.
  foreach ($old_rows as $row) {
    $values = (array) $row;

    $original_limit = $values['field_limit_value'] ?? 6;
    $new_limit = match(TRUE) {
      $original_limit <= 4 => 4,
      in_array($original_limit, [5, 6]) => 6,
      $original_limit >= 7 => 8,
    };
    $values['field_news_limit_value'] = $new_limit;
    unset($values['field_limit_value']);
    \Drupal::database()
      ->insert('paragraph__field_news_limit')
      ->fields($values)
      ->execute();
  }
}
