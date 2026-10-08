<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\TextServices;

use Drupal\helfi_ai\TextServices\SseParser;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests parsing server-sent events.
 */
#[Group('helfi_ai')]
#[CoversClass(SseParser::class)]
class SseParserTest extends UnitTestCase {

  /**
   * An event is returned once its terminating empty line has arrived.
   */
  public function testEventSplitAcrossChunks(): void {
    $parser = new SseParser();

    $this->assertSame([], $parser->feed("event: delta\nda"));
    $this->assertSame([], $parser->feed("ta: {\"text\": \"hei\"}\n"));
    $this->assertSame(
      [['event' => 'delta', 'data' => '{"text": "hei"}']],
      $parser->feed("\nevent: done\n"),
    );
  }

  /**
   * A multibyte character split between chunks is kept intact.
   */
  public function testMultibyteCharacterSplitAcrossChunks(): void {
    $parser = new SseParser();
    $bytes = "data: {\"text\": \"ä\"}\n\n";
    $split = strpos($bytes, 'ä') + 1;

    $this->assertSame([], $parser->feed(substr($bytes, 0, $split)));
    $this->assertSame(
      [['event' => 'message', 'data' => '{"text": "ä"}']],
      $parser->feed(substr($bytes, $split)),
    );
  }

  /**
   * A captured API stream parses into start, delta and done events.
   */
  public function testCapturedStream(): void {
    $events = (new SseParser())->feed((string) file_get_contents(__DIR__ . '/../../../fixtures/tone-stream.txt'));

    $this->assertSame('start', $events[0]['event']);
    $this->assertSame('done', end($events)['event']);
    $text = '';
    foreach ($events as $event) {
      if ($event['event'] === 'delta') {
        $text .= json_decode($event['data'], TRUE)['text'];
      }
    }
    $this->assertStringStartsWith('<h2>Asukaspysäköinti</h2>', $text);
  }

}
