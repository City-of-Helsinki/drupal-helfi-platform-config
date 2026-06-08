<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Token;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\metatag\MetatagManager;

/**
 * Resolves the customized metatag title of an entity.
 *
 * Editors can override the page title through the metatag fields. This service
 * returns that overridden title (with the configured tokens and leftover
 * separators stripped), or NULL when the entity has no customized title.
 */
final readonly class MetatagTitleResolver {

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
  public function resolve(ContentEntityInterface $entity): ?string {
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
    $title = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $value) ?? '';

    return $title !== '' ? $title : NULL;
  }

  /**
   * Removes the configured tokens from a raw metatag value.
   */
  private function stripTokens(string $value): string {
    return trim(str_replace(self::STRIP_TOKENS, '', $value));
  }

}
