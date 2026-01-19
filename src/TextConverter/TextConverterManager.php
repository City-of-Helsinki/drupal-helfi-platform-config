<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\TextConverter;

use Drupal\Core\Entity\EntityInterface;

/**
 * Service collector for text converters.
 */
class TextConverterManager {

  /**
   * Text converters.
   *
   * @var array
   */
  private array $converters = [];

  /**
   * Sorted text converters.
   *
   * @var array
   */
  private array $sortedConverters;

  /**
   * Adds a text converter.
   *
   * @param \Drupal\helfi_platform_config\TextConverter\TextConverterInterface $textConverter
   *   The text converter.
   * @param int $priority
   *   Text converter priority.
   *
   * @return $this
   *   Self.
   */
  public function add(TextConverterInterface $textConverter, int $priority = 0) : self {
    $this->converters[$priority][] = $textConverter;
    $this->sortedConverters = [];

    return $this;
  }

  /**
   * Convert a given entity to text.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to convert.
   *
   * @return string|null
   *   Text output or NULL if no suitable converter exists.
   */
  public function convert(EntityInterface $entity) : ?string {
    // Use the first applicable converter.
    foreach ($this->getTextConverters() as $converter) {
      if ($converter->applies($entity)) {
        return $converter->convert($entity);
      }
    }

    return NULL;
  }

  /**
   * Gets a sorted list of text converters.
   *
   * @return \Drupal\helfi_platform_config\TextConverter\TextConverterInterface[]
   *   Text converters sorted according to priority.
   */
  private function getTextConverters() : array {
    if (empty($this->sortedConverters)) {
      ksort($this->converters);
      $this->sortedConverters = array_merge(...$this->converters);
    }

    return $this->sortedConverters;
  }

}
