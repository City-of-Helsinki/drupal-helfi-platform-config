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
   * @param \Drupal\helfi_platform_config\TextConverter\Strategy $strategy
   *   Conversion strategy.
   *
   * @return string|null
   *   Text output or NULL if no suitable converter exists.
   */
  public function convert(EntityInterface $entity, Strategy $strategy = Strategy::Default) : ?string {
    // Use the first applicable converter.
    foreach ($this->getTextConverters() as $converter) {
      if ($converter->applies($entity, $strategy)) {
        return $converter->convert($entity, $strategy);
      }
    }

    return NULL;
  }

  /**
   * Convert a given entity to text chunks.
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
   *   Text chunks or empty array if no suitable converter exists.
   */
  public function chunk(EntityInterface $entity, Strategy $strategy = Strategy::Markdown, int $headerLevel = 2, array $context = []): array {
    foreach ($this->getTextConverters() as $converter) {
      if ($converter->applies($entity, $strategy)) {
        return $converter->chunk($entity, $strategy, $headerLevel, $context);
      }
    }

    return [];
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
