<?php

/**
 * @file
 * Contains content cards deploy hooks.
 */

declare(strict_types=1);

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\helfi_platform_config\MultisiteSearch;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Copies node references onto the multisite content field.
 *
 * Runs after config import, once the target field storage exists.
 * A node id 9180 in etusivu and in Finnish becomes
 * site_etusivu/entity:node/9180:fi.
 *
 * Important to note, that non-published nodes are not in the
 * search index, and even though the ids are migrated to the new field,
 * they will appear empty both in the edit form and in the view mode.
 */
function helfi_paragraphs_content_cards_deploy_migrate_content_field() : void {
  $prefix = \Drupal::service(MultisiteSearch::class)->getInstanceIndexPrefix();
  if ($prefix === NULL) {
    throw new \RuntimeException('Cannot migrate content card references without the current instance.');
  }

  $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'content_cards')
    ->exists('field_content_cards_content')
    ->execute();

  foreach (array_chunk($ids, 50, TRUE) as $chunk) {
    foreach ($storage->loadMultiple($chunk) as $paragraph) {
      assert($paragraph instanceof ParagraphInterface);
      if (!$paragraph->hasField('field_content_cards_content_ext')) {
        continue;
      }

      $changed = FALSE;
      foreach (array_keys($paragraph->getTranslationLanguages()) as $langcode) {
        $translation = $paragraph->getTranslation($langcode);
        $values = [];
        foreach ($translation->get('field_content_cards_content') as $item) {
          if (!$item instanceof EntityReferenceItem) {
            continue;
          }
          $node_id = $item->target_id;
          if ($node_id === NULL || $node_id === '') {
            continue;
          }
          $values[] = [
            'target_id' => $prefix . 'entity:node/' . $node_id . ':' . $item->getLangcode(),
          ];
        }
        if ($values === []) {
          continue;
        }

        $current = array_column($translation->get('field_content_cards_content_ext')->getValue(), 'target_id');
        $targets = array_column($values, 'target_id');
        if ($current === $targets) {
          continue;
        }

        $translation->set('field_content_cards_content_ext', $values);
        $changed = TRUE;
      }

      if (!$changed) {
        continue;
      }

      // Update the current revision in place. A new revision would leave the
      // parent pointing at the revision that still has an empty field.
      $paragraph->setSyncing(TRUE);
      $paragraph->setNewRevision(FALSE);
      $paragraph->save();
    }
    $storage->resetCache($chunk);
  }
}
