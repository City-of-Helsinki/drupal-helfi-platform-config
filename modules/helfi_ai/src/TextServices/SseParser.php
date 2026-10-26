<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\TextServices;

/**
 * Parses a server-sent events (SSE) stream into events.
 *
 * Input is buffered until a complete event (terminated by an empty line) has
 * arrived.
 */
final class SseParser {

  /**
   * Input not yet parsed into events.
   */
  private string $buffer = '';

  /**
   * Adds a chunk of the stream and returns the events it completed.
   *
   * @param string $chunk
   *   The next chunk of the stream.
   *
   * @return list<array{event: string, data: string}>
   *   The completed events.
   */
  public function feed(string $chunk): array {
    $this->buffer .= $chunk;
    $events = [];

    while (($end = strpos($this->buffer, "\n\n")) !== FALSE) {
      $block = substr($this->buffer, 0, $end);
      $this->buffer = substr($this->buffer, $end + 2);

      $event = ['event' => 'message', 'data' => ''];
      foreach (explode("\n", $block) as $line) {
        [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
        if ($field === 'event' || $field === 'data') {
          $event[$field] = ltrim($value);
        }
      }
      $events[] = $event;
    }
    return $events;
  }

}
