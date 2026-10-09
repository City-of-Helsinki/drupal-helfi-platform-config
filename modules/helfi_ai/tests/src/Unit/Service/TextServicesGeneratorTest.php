<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\helfi_ai\Service\TextServicesGenerator;
use Drupal\helfi_ai\TextServices\Service;
use Drupal\helfi_ai\TextServices\SseParser;
use Drupal\helfi_ai\TextServices\TextServicesClientInterface;
use Drupal\helfi_ai\TextServices\TextServicesException;
use Drupal\helfi_ai\TextServices\TextServicesRequest;
use Drupal\helfi_ai\TextServices\TextServicesResponse;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Tests generating suggestions with Helfi Text Services.
 */
#[Group('helfi_ai')]
#[CoversClass(TextServicesGenerator::class)]
class TextServicesGeneratorTest extends UnitTestCase {

  /**
   * The client mock.
   */
  private TextServicesClientInterface&MockObject $client;

  /**
   * The logger mock.
   */
  private LoggerInterface&MockObject $logger;

  /**
   * The converted entity content.
   */
  private ?string $content = 'Page content';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->client = $this->createMock(TextServicesClientInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);
  }

  /**
   * Creates the generator.
   */
  private function createGenerator(): TextServicesGenerator {
    $textConverterManager = $this->createMock(TextConverterManager::class);
    $textConverterManager->method('convert')->willReturnCallback(fn () => $this->content);

    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderInIsolation')
      ->with($this->callback(fn (array $build) => $build['#theme'] === 'item_list'))
      ->willReturnCallback(fn (array $build) => '<ul><li>' . implode('</li><li>', $build['#items']) . '</li></ul>');

    return new TextServicesGenerator($this->client, $textConverterManager, $renderer, $this->logger);
  }

  /**
   * Creates an entity in the given language.
   */
  private function createEntity(string $langcode = 'fi'): ContentEntityInterface {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn($langcode);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('language')->willReturn($language);
    return $entity;
  }

  /**
   * Streams the given text deltas, followed by a done event.
   *
   * @param string[] $deltas
   *   The deltas.
   */
  private function streamDeltas(array $deltas): void {
    $events = array_map(fn (string $text) => ['event' => 'delta', 'data' => ['text' => $text]], $deltas);
    $events[] = ['event' => 'done', 'data' => ['finish_reason' => 'stop']];
    $this->client->method('stream')->willReturnCallback(fn () => yield from $events);
  }

  /**
   * Collects the JSON data of streamed server-sent events.
   *
   * @param \Generator<int, \Symfony\Component\HttpFoundation\ServerEvent> $events
   *   The streamed events.
   *
   * @return array<int, array<string, mixed>>
   *   The decoded event data.
   */
  private function collect(\Generator $events): array {
    $data = [];
    foreach ($events as $event) {
      $json = $event->getData();
      $this->assertIsString($json);
      $data[] = json_decode($json, TRUE);
    }
    return $data;
  }

  /**
   * Streams the events of a captured API stream fixture.
   *
   * @return \Generator<int, array{event: string, data: array<mixed>}>
   *   The events.
   */
  private function fixtureEvents(string $name): \Generator {
    foreach ((new SseParser())->feed((string) file_get_contents(__DIR__ . '/../../../fixtures/' . $name)) as $event) {
      yield ['event' => $event['event'], 'data' => json_decode($event['data'], TRUE)];
    }
  }

  /**
   * A captured API stream yields each part of the rewrite, then all of it.
   */
  public function testStreamTone(): void {
    $this->client->expects($this->once())
      ->method('stream')
      ->with(new TextServicesRequest(Service::Tone, '<p>Teksti</p>', 'sv'))
      ->willReturn($this->fixtureEvents('tone-stream.txt'));

    $data = $this->collect($this->createGenerator()->streamTone('<p>Teksti</p>', 'sv'));
    $done = array_pop($data);

    $this->assertSame(['delta' => '<h'], $data[0]);
    $this->assertTrue($done['done']);
    $this->assertSame(implode('', array_column($data, 'delta')), $done['result']);
    $this->assertStringStartsWith('<h2>Asukaspysäköinti</h2>', $done['result']);
  }

  /**
   * Empty deltas are skipped, and an empty rewrite is an error.
   */
  public function testStreamToneEmpty(): void {
    $this->streamDeltas(['', ' ']);
    $this->logger->expects($this->once())->method('log')
      ->with(LogLevel::ERROR, $this->anything(), $this->callback(fn (array $context) => $context['@message'] === 'Empty rewrite from Helfi Text Services'));

    $this->assertSame([['delta' => ' '], ['error' => TRUE]], $this->collect($this->createGenerator()->streamTone('<p>Teksti</p>', 'fi')));
  }

  /**
   * A failed stream yields an error event after the parts so far.
   */
  public function testStreamToneFailure(): void {
    $this->client->method('stream')->willReturnCallback(function (): \Generator {
      yield ['event' => 'delta', 'data' => ['text' => '<p>Osa']];
      throw new TextServicesException('Stream failed');
    });
    $this->logger->expects($this->once())->method('log')
      ->with(LogLevel::ERROR, $this->anything(), $this->callback(fn (array $context) => $context['@message'] === 'Stream failed'));

    $this->assertSame([['delta' => '<p>Osa'], ['error' => TRUE]], $this->collect($this->createGenerator()->streamTone('<p>Teksti</p>', 'fi')));
  }

  /**
   * The summary input is the trimmed entity text, cut to the maximum length.
   */
  public function testSummaryInput(): void {
    $this->content = "  Page content\n";
    $this->assertSame('Page content', $this->createGenerator()->summaryInput($this->createEntity()));

    $this->content = str_repeat('ä', TextServicesGenerator::MAX_CONTENT_LENGTH + 100);
    $this->assertSame(TextServicesGenerator::MAX_CONTENT_LENGTH, mb_strlen((string) $this->createGenerator()->summaryInput($this->createEntity())));
  }

  /**
   * An entity without content has no summary input and gets no titles.
   */
  #[TestWith([NULL])]
  #[TestWith(['  '])]
  public function testEntityWithoutContent(?string $content): void {
    $this->content = $content;
    $this->client->expects($this->never())->method('send');
    $generator = $this->createGenerator();

    $this->assertNull($generator->summaryInput($this->createEntity()));
    $this->assertSame([], $generator->suggestTitles($this->createEntity()));
  }

  /**
   * Title suggestions are the first three summary items.
   */
  public function testSuggestTitles(): void {
    $this->client->expects($this->once())
      ->method('send')
      ->with($this->callback(fn (TextServicesRequest $request) => $request->service === Service::Summarize
        && $request->text === 'Page content'
        && $request->langcode === 'sv'
        && $request->jsonSchema !== NULL))
      ->willReturn(new TextServicesResponse('{"items": ["One", "Two", "Three", "Four"]}', 'gpt-5.1'));

    $this->assertSame(['One', 'Two', 'Three'], $this->createGenerator()->suggestTitles($this->createEntity('sv')));
  }

  /**
   * A response without a valid item list is logged as an error.
   */
  #[TestWith(['not json'])]
  #[TestWith(['{"summary": ["First"]}'])]
  #[TestWith(['{"items": "First"}'])]
  #[TestWith(['{"items": [1, 2]}'])]
  public function testInvalidItems(string $text): void {
    $this->client->method('send')->willReturn(new TextServicesResponse($text, 'gpt-5.1'));
    $this->logger->expects($this->once())->method('log')
      ->with(LogLevel::ERROR, $this->anything(), $this->callback(fn (array $context) => $context['@message'] === 'Invalid summary items from Helfi Text Services'));

    $this->assertSame([], $this->createGenerator()->suggestTitles($this->createEntity()));
  }

  /**
   * A failed request is logged and yields no result.
   */
  public function testRequestFailure(): void {
    $this->client->method('send')->willThrowException(new TextServicesException('Request failed'));
    $this->logger->expects($this->once())->method('log')
      ->with(LogLevel::ERROR, $this->anything(), $this->callback(fn (array $context) => $context['@message'] === 'Request failed'));

    $this->assertSame([], $this->createGenerator()->suggestTitles($this->createEntity()));
  }

  /**
   * A captured API stream yields each item once it is complete, then the list.
   */
  public function testStreamSummary(): void {
    $this->client->expects($this->once())
      ->method('stream')
      ->with($this->callback(fn (TextServicesRequest $request) => $request->service === Service::Summarize
        && $request->text === 'Page content'
        && $request->langcode === 'sv'
        && $request->jsonSchema !== NULL))
      ->willReturn($this->fixtureEvents('summarize-stream.txt'));

    $data = $this->collect($this->createGenerator()->streamSummary('Page content', 'sv'));
    $done = array_pop($data);

    // One event per completed item, each list growing by one.
    $this->assertSame([1, 2, 3, 4, 5], array_map(fn (array $event) => count($event['items']), $data));
    $this->assertSame('Haet asukaspysäköintitunnuksen sähköisesti Helsingin asiointipalvelussa, joten sinun ei tarvitse asioida paikan päällä.', $data[0]['items'][0]);
    $this->assertTrue($done['done']);
    $this->assertSame(5, substr_count($done['result'], '<li>'));
  }

  /**
   * An escaped quote inside an item doesn't end the item.
   */
  public function testStreamSummaryEscapedQuote(): void {
    $this->streamDeltas(['{"items": ["Sano \\"hei', '\\" ja', ' mene", "Toi', 'nen"]}']);

    $data = $this->collect($this->createGenerator()->streamSummary('Page content', 'fi'));

    $this->assertSame([['items' => ['Sano "hei" ja mene']], ['items' => ['Sano "hei" ja mene', 'Toinen']]], array_slice($data, 0, 2));
    $this->assertTrue($data[2]['done']);
  }

  /**
   * An invalid or empty summary yields an error event and is logged.
   */
  #[TestWith(['{"items": "First"}', 'Invalid summary items from Helfi Text Services'])]
  #[TestWith(['{"items": []}', 'Empty summary from Helfi Text Services'])]
  public function testStreamSummaryInvalid(string $json, string $message): void {
    $this->streamDeltas([$json]);
    $this->logger->expects($this->once())->method('log')
      ->with(LogLevel::ERROR, $this->anything(), $this->callback(fn (array $context) => $context['@message'] === $message));

    $this->assertSame([['error' => TRUE]], $this->collect($this->createGenerator()->streamSummary('Page content', 'fi')));
  }

  /**
   * A failed stream yields an error event after the items so far.
   */
  public function testStreamSummaryFailure(): void {
    $this->client->method('stream')->willReturnCallback(function (): \Generator {
      yield ['event' => 'delta', 'data' => ['text' => '{"items": ["First", "Sec']];
      throw new TextServicesException('Stream failed');
    });
    $this->logger->expects($this->once())->method('log')
      ->with(LogLevel::ERROR, $this->anything(), $this->callback(fn (array $context) => $context['@message'] === 'Stream failed'));

    $this->assertSame([['items' => ['First']], ['error' => TRUE]], $this->collect($this->createGenerator()->streamSummary('Page content', 'fi')));
  }

}
