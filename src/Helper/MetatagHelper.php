<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Helper;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\metatag\MetatagManager;

/**
 * Reads metatag values customized on an entity.
 */
final readonly class MetatagHelper {

  /**
   * Tokens to remove from the title template.
   */
  private const array STRIP_TOKENS = ['[site:page-title-suffix]'];

  public function __construct(
    private ?MetatagManager $metatagManager = NULL,
  ) {
  }

  /**
   * Resolves the customized metatag title for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to resolve the title for.
   *
   * @return string|null
   *   The customized metatag title, or NULL when the title has not been
   *   customized (or metatag is not installed).
   */
  public function resolveTitle(ContentEntityInterface $entity): ?string {
    if (!$this->metatagManager) {
      return NULL;
    }

    // Only the tags set on the entity itself are resolved, so the title is
    // empty unless an editor has customized it.
    $tags = $this->metatagManager->tagsFromEntity($entity);

    if (empty($tags['title'])) {
      return NULL;
    }

    // Strip configured tokens from the template so they are never resolved
    // into the indexed title.
    $tags['title'] = $this->stripTokens($tags['title']);

    $value = $this->metatagManager->generateTokenValues($tags, $entity)['title'] ?? '';

    if (!is_string($value)) {
      return NULL;
    }

    // Trim non-word characters from both ends, removing leftover separators.
    $title = preg_replace('/(?:^[^\p{L}\p{N}]+)|(?:[^\p{L}\p{N}]+$)/u', '', $value) ?? '';

    return $title !== '' ? $title : NULL;
  }

  /**
   * Checks whether the entity has the robots "noindex" directive set.
   *
   * Only the tags set on the entity itself are inspected. Entity-type and
   * global defaults don't cause a page to be reported as noindex.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE when the entity is set to noindex.
   */
  public function isNoindex(ContentEntityInterface $entity): bool {
    if (!$this->metatagManager) {
      return FALSE;
    }

    $tags = $this->metatagManager->tagsFromEntity($entity);

    if (empty($tags['robots']) || !is_string($tags['robots'])) {
      return FALSE;
    }

    // The robots tag is stored as a comma-separated list of directives, e.g.
    // "noindex, nofollow".
    $directives = array_map('trim', explode(',', $tags['robots']));

    return in_array('noindex', $directives, TRUE);
  }

  /**
   * Removes the configured tokens from a raw metatag value.
   */
  private function stripTokens(string $value): string {
    return trim(str_replace(self::STRIP_TOKENS, '', $value));
  }

}
