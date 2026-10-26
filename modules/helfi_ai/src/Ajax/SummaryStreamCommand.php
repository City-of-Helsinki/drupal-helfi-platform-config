<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Starts streaming an AI summary into the summary widget.
 *
 * @see js/ai-summary-stream.js
 */
final class SummaryStreamCommand implements CommandInterface {

  public function __construct(
    private readonly string $wrapperId,
    private readonly string $text,
    private readonly string $langcode,
    private readonly string $url,
    private readonly string $csrfToken,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @return array<string, string>
   *   The command data.
   */
  public function render(): array {
    return [
      'command' => 'helfiAiSummaryStream',
      'wrapperId' => $this->wrapperId,
      'text' => $this->text,
      'langcode' => $this->langcode,
      'url' => $this->url,
      'csrfToken' => $this->csrfToken,
    ];
  }

}
