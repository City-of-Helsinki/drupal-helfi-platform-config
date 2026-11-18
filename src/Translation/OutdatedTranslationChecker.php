<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Translation;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Detects translations that are outdated compared to the original.
 *
 * A translation is outdated when it was last edited more than two weeks
 * before the original (default) translation. The comparison uses the
 * 'changed_at' field, which is only updated when content is saved through a
 * form, so programmatic saves do not affect the result. Entities without the
 * field, or without a value in it, are never outdated.
 *
 * @see \Drupal\helfi_platform_config\Hook\ChangedAtFieldHooks
 */
class OutdatedTranslationChecker {

  /**
   * The field that holds the time the content was last edited.
   */
  public const string FIELD = 'changed_at';

  /**
   * One day in seconds.
   */
  private const int DAY = 86400;

  /**
   * How much older than the original a translation can be, in seconds.
   */
  public const int THRESHOLD = 14 * self::DAY;

  /**
   * Gets the number of days the translation is older than the original.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation.
   *
   * @return int|null
   *   Full days the translation is older than the original, or NULL if the
   *   translation is not outdated.
   */
  public function getOutdatedDays(ContentEntityInterface $translation): ?int {
    if ($translation->isDefaultTranslation() || $translation->isNewTranslation()) {
      return NULL;
    }

    $translated = $this->getChangedTime($translation);
    $original = $this->getChangedTime($translation->getUntranslated());

    if ($translated === NULL || $original === NULL) {
      return NULL;
    }

    $difference = $original - $translated;

    return $difference > self::THRESHOLD ? intdiv($difference, self::DAY) : NULL;
  }

  /**
   * Gets the outdated translations of the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array<string, int>
   *   Full days each outdated translation is older than the original, keyed
   *   by langcode.
   */
  public function getOutdatedTranslations(ContentEntityInterface $entity): array {
    $outdated = [];

    foreach (array_keys($entity->getTranslationLanguages(FALSE)) as $langcode) {
      $days = $this->getOutdatedDays($entity->getTranslation($langcode));

      if ($days !== NULL) {
        $outdated[$langcode] = $days;
      }
    }

    return $outdated;
  }

  /**
   * Gets the time the translation was last edited.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation.
   *
   * @return int|null
   *   The timestamp, or NULL if the entity has no value for it.
   */
  public function getChangedTime(ContentEntityInterface $translation): ?int {
    if (!$translation->hasField(self::FIELD)) {
      return NULL;
    }

    $value = $translation->get(self::FIELD)->value;

    return is_numeric($value) ? (int) $value : NULL;
  }

}
