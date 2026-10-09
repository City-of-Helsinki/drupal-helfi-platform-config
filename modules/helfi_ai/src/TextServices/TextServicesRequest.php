<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\TextServices;

/**
 * A request to a Helfi Text Services service.
 */
final readonly class TextServicesRequest {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\helfi_ai\TextServices\Service $service
   *   The service to call.
   * @param string $text
   *   The text to process.
   * @param string $langcode
   *   The language of the text. Not sent until the API supports it.
   * @param array<string, mixed>|null $jsonSchema
   *   A JSON schema the response data must follow, or NULL for plain text.
   */
  public function __construct(
    public Service $service,
    public string $text,
    public string $langcode,
    public ?array $jsonSchema = NULL,
  ) {
  }

}
