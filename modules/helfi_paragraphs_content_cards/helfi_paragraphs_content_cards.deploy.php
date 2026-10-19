<?php

/**
 * @file
 * Contains content cards deploy hooks.
 */

declare(strict_types=1);

use Drupal\helfi_platform_config\MultisiteSearch;

/**
 * Copies node references onto the multisite content field.
 *
 * Runs after config import, once the target field storage exists.
 * A node id 9180 in etusivu and in Finnish becomes
 * site_etusivu/entity:node/9180:fi.
 */
function helfi_paragraphs_content_cards_deploy_migrate_content_references() : void {
  $prefix = \Drupal::service(MultisiteSearch::class)->getInstanceIndexPrefix();
  if ($prefix === NULL) {
    throw new \RuntimeException('Cannot migrate content card references without the current instance.');
  }

  $database = \Drupal::database();
  $tables = [
    'paragraph__field_content_cards_content' => 'paragraph__field_content_cards_content_ext',
    'paragraph_revision__field_content_cards_content' => 'paragraph_revision__field_content_cards_content_ext',
  ];
  foreach ($tables as $source => $target) {
    if (!$database->schema()->tableExists($source) || !$database->schema()->tableExists($target)) {
      continue;
    }
    _helfi_paragraphs_content_cards_copy_references($source, $target, $prefix);
  }
}

/**
 * Copies one field table into the multisite content field table.
 */
function _helfi_paragraphs_content_cards_copy_references(string $source, string $target, string $prefix) : void {
  $database = \Drupal::database();
  $rows = $database->select($source, 'source')
    ->fields('source')
    ->execute();

  foreach ($rows as $row) {
    $node_id = $row->field_content_cards_content_target_id;
    if ($node_id === NULL || $node_id === '') {
      continue;
    }

    $exists = $database->select($target, 'target')
      ->condition('entity_id', $row->entity_id)
      ->condition('revision_id', $row->revision_id)
      ->condition('langcode', $row->langcode)
      ->condition('delta', $row->delta)
      ->condition('deleted', $row->deleted)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($exists) {
      continue;
    }

    $database->insert($target)
      ->fields([
        'bundle' => $row->bundle,
        'deleted' => $row->deleted,
        'entity_id' => $row->entity_id,
        'revision_id' => $row->revision_id,
        'langcode' => $row->langcode,
        'delta' => $row->delta,
        'field_content_cards_content_ext_target_id' => $prefix . 'entity:node/' . $node_id . ':' . $row->langcode,
      ])
      ->execute();
  }
}
