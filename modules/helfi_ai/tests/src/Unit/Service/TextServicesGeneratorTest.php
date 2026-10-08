<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\helfi_ai\Service\TextServicesGenerator;
use Drupal\helfi_ai\TextServices\Service;
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
      ->with($this->callback(fn (array $build) => $build['#theme'] === 'item_list' && $build['#list_type'] === 'ul'))
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
   * Makes the client return the given summary items.
   *
   * @param string[] $items
   *   The items.
   */
  private function respondWithItems(array $items): void {
    $this->client->method('send')
      ->willReturn(new TextServicesResponse(json_encode(['items' => $items], JSON_THROW_ON_ERROR), 'gpt-5.1'));
  }

  /**
   * The tone check sends the content and language and returns the rewrite.
   */
  public function testCheckTone(): void {
    $this->client->expects($this->once())
      ->method('send')
      ->with(new TextServicesRequest(Service::Tone, '<p>Teksti</p>', 'sv'))
      ->willReturn(new TextServicesResponse('<p>Parempi teksti</p>', 'gpt-5.1'));

    $this->assertSame('<p>Parempi teksti</p>', $this->createGenerator()->checkTone('<p>Teksti</p>', 'sv'));
  }

  /**
   * The summary is requested as items and rendered as a list.
   */
  public function testGenerateSummary(): void {
    $this->client->expects($this->once())
      ->method('send')
      ->with($this->callback(fn (TextServicesRequest $request) => $request->service === Service::Summarize
        && $request->text === 'Page content'
        && $request->langcode === 'sv'
        && $request->jsonSchema !== NULL))
      ->willReturn(new TextServicesResponse('{"items": ["First", "Second"]}', 'gpt-5.1'));

    $this->assertSame('<ul><li>First</li><li>Second</li></ul>', $this->createGenerator()->generateSummary($this->createEntity('sv')));
  }

  /**
   * Content longer than the API accepts is cut to the maximum length.
   */
  public function testLongContentIsCut(): void {
    $this->content = str_repeat('ä', TextServicesGenerator::MAX_CONTENT_LENGTH + 100);
    $this->client->expects($this->once())
      ->method('send')
      ->with($this->callback(fn (TextServicesRequest $request) => mb_strlen($request->text) === TextServicesGenerator::MAX_CONTENT_LENGTH))
      ->willReturn(new TextServicesResponse('{"items": ["First"]}', 'gpt-5.1'));

    $this->createGenerator()->generateSummary($this->createEntity());
  }

  /**
   * An entity without content is not sent.
   */
  #[TestWith([NULL])]
  #[TestWith(['  '])]
  public function testEntityWithoutContent(?string $content): void {
    $this->content = $content;
    $this->client->expects($this->never())->method('send');
    $generator = $this->createGenerator();

    $this->assertNull($generator->generateSummary($this->createEntity()));
    $this->assertSame([], $generator->suggestTitles($this->createEntity()));
  }

  /**
   * An empty item list yields no summary.
   */
  public function testEmptySummary(): void {
    $this->respondWithItems([]);

    $this->assertNull($this->createGenerator()->generateSummary($this->createEntity()));
  }

  /**
   * Title suggestions are the first three items.
   */
  public function testSuggestTitles(): void {
    $this->respondWithItems(['One', 'Two', 'Three', 'Four']);

    $this->assertSame(['One', 'Two', 'Three'], $this->createGenerator()->suggestTitles($this->createEntity()));
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
    $this->logger->expects($this->exactly(2))->method('log')
      ->with(LogLevel::ERROR, $this->anything(), $this->callback(fn (array $context) => $context['@message'] === 'Invalid summary items from Helfi Text Services'));
    $generator = $this->createGenerator();

    $this->assertNull($generator->generateSummary($this->createEntity()));
    $this->assertSame([], $generator->suggestTitles($this->createEntity()));
  }

  /**
   * A failed request is logged and yields no result.
   */
  public function testRequestFailure(): void {
    $this->client->method('send')->willThrowException(new TextServicesException('Request failed'));
    $this->logger->expects($this->exactly(3))->method('log')
      ->with(LogLevel::ERROR, $this->anything(), $this->callback(fn (array $context) => $context['@message'] === 'Request failed'));
    $generator = $this->createGenerator();

    $this->assertNull($generator->checkTone('<p>Teksti</p>', 'fi'));
    $this->assertNull($generator->generateSummary($this->createEntity()));
    $this->assertSame([], $generator->suggestTitles($this->createEntity()));
  }

}
