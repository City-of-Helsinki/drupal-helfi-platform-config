<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI\Processor;

/**
 * A DTO to represent 'main image' Search API processor properties.
 */
final readonly class MainImageProcessorProperties {

  public function __construct(
    public string $imageStyleField,
    public string $entityField,
  ) {
  }

}
