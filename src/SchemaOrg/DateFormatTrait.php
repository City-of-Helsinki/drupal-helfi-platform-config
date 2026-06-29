<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SchemaOrg;

use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Formats timestamps as ISO 8601 strings for schema.org output.
 */
trait DateFormatTrait {

  /**
   * The date formatter.
   */
  protected ?DateFormatterInterface $dateFormatter = NULL;

  /**
   * Formats a timestamp as an ISO 8601 string.
   *
   * @param int|string $timestamp
   *   The UNIX timestamp.
   *
   * @return string
   *   ISO 8601 date string.
   */
  protected function formatDate(int|string $timestamp): string {
    return $this->getDateFormatter()->format((int) $timestamp, 'custom', 'c');
  }

  /**
   * Gets the date formatter, loading it from the container when unset.
   */
  protected function getDateFormatter(): DateFormatterInterface {
    if (!$this->dateFormatter) {
      $this->dateFormatter = \Drupal::service(DateFormatterInterface::class);
    }
    return $this->dateFormatter;
  }

  /**
   * Sets the date formatter.
   */
  public function setDateFormatter(DateFormatterInterface $dateFormatter): static {
    $this->dateFormatter = $dateFormatter;
    return $this;
  }

}
