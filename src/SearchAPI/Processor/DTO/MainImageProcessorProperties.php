<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI\Processor;

final readonly class MainImageProcessorProperties {

  public function __construct(
    public string $searchApiField,
    public string $entityField,
  ) {
  }

}

