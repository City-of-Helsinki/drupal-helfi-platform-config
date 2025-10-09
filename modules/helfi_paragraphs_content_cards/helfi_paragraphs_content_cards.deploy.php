<?php

/**
 * @file
 * Contains content cards paragraph deploy hooks.
 */

declare(strict_types=1);

/**
 * Migrate the field_limit values to field_news_limit in deploy hook.
 */
function helfi_paragraphs_content_cards_deploy_content_cards_design_migration(): void {
  $old_rows = \Drupal::database()
    ->select('paragraph__field_content_cards_design')
    ->fields('paragraph__field_content_cards_design')
    ->condition('bundle', 'content_cards')
    ->execute()
    ->fetchAll();

  if (!$old_rows) {
    return;
  }

  foreach ($old_rows as $row) {
    $values = (array) $row;
    $original_design = $values['field_content_cards_design_value'] ?? NULL;

    // Skip if there's no value.
    if (!$original_design) {
      continue;
    }

    // Map the old grey styles to the new ones.
    $new_design = match ($original_design) {
      'small-cards-grey' => 'small-cards',
      'large-cards-grey' => 'large-cards',
      default => $original_design,
    };

    // Skip if nothing changed.
    if ($new_design === $original_design) {
      continue;
    }

    // Update the record.
    \Drupal::database()
      ->update('paragraph__field_content_cards_design')
      ->fields(['field_content_cards_design_value' => $new_design])
      ->condition('entity_id', $values['entity_id'])
      ->condition('revision_id', $values['revision_id'])
      ->execute();
  }
}
