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
   *
   * @return bool
   *   TRUE if this converter applies to given entity.
   */
  public function applies(EntityInterface $entity) : bool;

  /**
   * Converts given entity to raw text.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to convert.
   *
   * @return string
   *   Entity converted to text.
   */
  public function convert(EntityInterface $entity) : string;

}
