<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\TextConverter;

use Drupal\Core\Entity\EntityInterface;

/**
 * Text converter interface.
 *
 * Recommendation engine only accepts UTF-8 encoded raw text and does not
 * understand HTML or Drupal specific data structures. Other modules can
 * implement TextConverters that translate Drupal entities to raw text
 * which can be fed to the language model.
 */
interface TextConverterInterface {

  /**
   * Checks whether this converter is applicable.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to convert.
   * @param \Drupal\helfi_platform_config\TextConverter\Strategy $strategy
   *   Conversion strategy.
   *
   * @return bool
   *   TRUE if this converter applies to given entity.
   */
  public function applies(EntityInterface $entity, Strategy $strategy) : bool;

  /**
   * Converts given entity to raw text.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to convert.
   * @param \Drupal\helfi_platform_config\TextConverter\Strategy $strategy
   *   Conversion strategy.
   *
   * @return string
   *   Entity converted to text.
   */
  public function convert(EntityInterface $entity, Strategy $strategy) : string;

  /**
   * Converts given entity to text chunks.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to convert.
   * @param \Drupal\helfi_platform_config\TextConverter\Strategy $strategy
   *   Conversion strategy.
   * @param int $headerLevel
   *   The heading level to split on (e.g. 2 for ##).
   * @param string[] $context
   *   Additional context lines to prepend to every chunk.
   *
   * @return string[]
   *   Entity text split into chunks.
   */
  public function chunk(EntityInterface $entity, Strategy $strategy, int $headerLevel = 2, array $context = []): array;

}
