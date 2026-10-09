<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\TextServices;

/**
 * A response from a Helfi Text Services service.
 */
final readonly class TextServicesResponse {

  /**
   * Constructs a new instance.
   *
   * @param string|null $text
   *   The generated text, or NULL when a JSON schema was requested.
   * @param array<mixed>|null $data
   *   The generated object matching the requested JSON schema, or NULL.
   */
  public function __construct(
    public ?string $text,
    public ?array $data = NULL,
  ) {
  }

  /**
   * Creates a response from decoded API response data.
   *
   * @param array<mixed> $data
   *   The decoded response body.
   *
   * @return self
   *   The response.
   *
   * @throws \Drupal\helfi_ai\TextServices\TextServicesException
   *   When the data is not a valid response.
   */
  public static function fromArray(array $data): self {
    $text = $data['text'] ?? NULL;
    $object = $data['data'] ?? NULL;

    if (!is_string($text) && !is_array($object)) {
      throw new TextServicesException('Invalid response from Helfi Text Services');
    }
    return new self(is_string($text) ? $text : NULL, is_array($object) ? $object : NULL);
  }

}
