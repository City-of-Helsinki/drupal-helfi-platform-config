<?php

declare(strict_types=1);

namespace Drupal\helfi_users;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Manages the content_translation_outdated flag for translated nodes.
 *
 * When the original language of a node is saved, its translations are flagged
 * as outdated if they have not been saved within OUTDATED_THRESHOLD_SECONDS.
 * The flag is cleared when a translation is saved with a changed timestamp
 * newer than the original.
 *
 * Flags are written directly to the database to avoid a full entity save cycle.
 */
class TranslationOutdatedManager {

  /** Grace period before translations are considered outdated after an original-language save. */
  const OUTDATED_THRESHOLD_SECONDS = 10;

  public function __construct(
    private readonly TimeInterface $time,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function updateOutdatedFlags(NodeInterface $entity): void {
    $translations = $entity->getTranslationLanguages();
    if (count($translations) < 2) {
      return;
    }

    $originalLangcode = $entity->getUntranslated()->language()->getId();
    $originalChanged = $entity->getTranslation($originalLangcode)->getChangedTime();
    $gracePeriodPassed = $this->time->getRequestTime() - $originalChanged >= self::OUTDATED_THRESHOLD_SECONDS;

    $needsInvalidation = FALSE;
    foreach (array_keys($translations) as $langcode) {
      if ($langcode === $originalLangcode) {
        continue;
      }

      $translation = $entity->getTranslation($langcode);
      $isOutdated = $gracePeriodPassed && $translation->getChangedTime() < $originalChanged;
      $currentValue = (bool) $translation->get('content_translation_outdated')->value;

      if ($currentValue !== $isOutdated) {
        $value = (int) $isOutdated;
        $this->database->update('node_field_data')
          ->fields(['content_translation_outdated' => $value])
          ->condition('nid', $entity->id())
          ->condition('langcode', $langcode)
          ->execute();

        $this->database->update('node_field_revision')
          ->fields(['content_translation_outdated' => $value])
          ->condition('nid', $entity->id())
          ->condition('vid', $entity->getRevisionId())
          ->condition('langcode', $langcode)
          ->execute();

        $needsInvalidation = TRUE;
      }
    }

    if ($needsInvalidation) {
      $this->entityTypeManager->getStorage('node')->resetCache([$entity->id()]);
      Cache::invalidateTags(['node:' . $entity->id()]);
    }
  }

}
