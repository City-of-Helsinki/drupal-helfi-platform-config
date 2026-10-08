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
   * @param string $text
   *   The generated text. A JSON string when a JSON schema was requested.
   * @param string $model
   *   The model that generated the text.
   */
  public function __construct(
    public string $text,
    public string $model,
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
    if (!is_string($data['text'] ?? NULL) || !is_string($data['model'] ?? NULL)) {
      throw new TextServicesException('Invalid response from Helfi Text Services');
    }
    return new self($data['text'], $data['model']);
  }

}
