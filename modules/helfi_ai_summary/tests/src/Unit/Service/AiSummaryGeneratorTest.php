<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai_summary\Unit\Service;

use Drupal\ai\Entity\AiPromptInterface;
use Drupal\helfi_ai_summary\Service\AiChatProviderInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_ai_summary\Service\AiSummaryGenerator;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\helfi_ai_summary\Service\AiSummaryGenerator
 * @group helfi_ai_summary
 */
class AiSummaryGeneratorTest extends UnitTestCase {

  /**
   * The AI chat provider mock.
   *
   * @var \Drupal\helfi_ai_summary\Service\AiChatProviderInterface
   */
  private AiChatProviderInterface $aiProvider;

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The text converter manager mock.
   *
   * @var \Drupal\helfi_platform_config\TextConverter\TextConverterManager
   */
  private TextConverterManager $textConverterManager;

  /**
   * The logger mock.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * The generator under test.
   *
   * @var \Drupal\helfi_ai_summary\Service\AiSummaryGenerator
   */
  private AiSummaryGenerator $generator;

  /**
   * The entity mock.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  private ContentEntityInterface $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->aiProvider = $this->prophesize(AiChatProviderInterface::class)->reveal();
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class)->reveal();
    $this->textConverterManager = $this->prophesize(TextConverterManager::class)->reveal();
    $this->logger = $this->prophesize(LoggerInterface::class)->reveal();

    $this->generator = new AiSummaryGenerator(
      $this->aiProvider,
      $this->entityTypeManager,
      $this->textConverterManager,
      $this->logger,
    );

    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn('fi');
    $language->getName()->willReturn('Finnish');

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity->hasTranslation('fi')->willReturn(FALSE);
    $entity->language()->willReturn($language->reveal());
    $entity->getEntityTypeId()->willReturn('node');
    $entity->id()->willReturn('1');
    $this->entity = $entity->reveal();
  }

  /**
   * @covers ::generate
   */
  public function testGenerateReturnsNullWhenContentIsEmpty(): void {
    $textConverter = $this->prophesize(TextConverterManager::class);
    $textConverter->convert($this->entity)->willReturn(NULL);

    $logger = $this->prophesize(LoggerInterface::class);
    $logger->warning(Argument::type('string'), Argument::type('array'))->shouldBeCalled();

    $generator = new AiSummaryGenerator(
      $this->aiProvider,
      $this->entityTypeManager,
      $textConverter->reveal(),
      $logger->reveal(),
    );

    $this->assertNull($generator->generate($this->entity, 'fi'));
  }

  /**
   * @covers ::generate
   */
  public function testGenerateReturnsNullWhenPromptNotFound(): void {
    $textConverter = $this->prophesize(TextConverterManager::class);
    $textConverter->convert($this->entity)->willReturn('Page content here.');

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load('helfi_content_summary__helfi_content_summary_default')->willReturn(NULL);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('ai_prompt')->willReturn($storage->reveal());

    $logger = $this->prophesize(LoggerInterface::class);
    $logger->error(Argument::type('string'))->shouldBeCalled();

    $generator = new AiSummaryGenerator(
      $this->aiProvider,
      $entityTypeManager->reveal(),
      $textConverter->reveal(),
      $logger->reveal(),
    );

    $this->assertNull($generator->generate($this->entity, 'fi'));
  }

  /**
   * @covers ::generate
   */
  public function testGenerateReturnsNullWhenAiThrows(): void {
    $textConverter = $this->prophesize(TextConverterManager::class);
    $textConverter->convert($this->entity)->willReturn('Page content here.');

    $prompt = $this->prophesize(AiPromptInterface::class);
    $prompt->getPrompt()->willReturn('Summarize {content} in {language}.');

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load('helfi_content_summary__helfi_content_summary_default')->willReturn($prompt->reveal());

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('ai_prompt')->willReturn($storage->reveal());

    $aiProvider = $this->prophesize(AiChatProviderInterface::class);
    $aiProvider->getSetProvider('chat')->willThrow(new \RuntimeException('Provider not configured'));

    $logger = $this->prophesize(LoggerInterface::class);
    $logger->error(Argument::type('string'), Argument::type('array'))->shouldBeCalled();

    $generator = new AiSummaryGenerator(
      $aiProvider->reveal(),
      $entityTypeManager->reveal(),
      $textConverter->reveal(),
      $logger->reveal(),
    );

    $this->assertNull($generator->generate($this->entity, 'fi'));
  }

  /**
   * @covers ::generate
   */
  public function testGenerateReturnsBulletList(): void {
    $textConverter = $this->prophesize(TextConverterManager::class);
    $textConverter->convert($this->entity)->willReturn('Page content here.');

    $prompt = $this->prophesize(AiPromptInterface::class);
    $prompt->getPrompt()->willReturn('Summarize {content} in {language}.');

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load('helfi_content_summary__helfi_content_summary_default')->willReturn($prompt->reveal());

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('ai_prompt')->willReturn($storage->reveal());

    $chatMessage = new ChatMessage('assistant', "First point\nSecond point\nThird point");
    $chatOutput = new ChatOutput($chatMessage, '', []);

    $chatProvider = $this->prophesize(ChatInterface::class);
    $chatProvider->chat(Argument::type(ChatInput::class), 'gpt-4o')->willReturn($chatOutput);

    $aiProvider = $this->prophesize(AiChatProviderInterface::class);
    $aiProvider->getSetProvider('chat')->willReturn([
      'provider_id' => $chatProvider->reveal(),
      'model_id' => 'gpt-4o',
    ]);

    $generator = new AiSummaryGenerator(
      $aiProvider->reveal(),
      $entityTypeManager->reveal(),
      $textConverter->reveal(),
      $this->logger,
    );

    $result = $generator->generate($this->entity, 'fi');
    $this->assertNotNull($result);
    $this->assertStringStartsWith('<ul>', $result);
    $this->assertStringContainsString('<li>First point</li>', $result);
    $this->assertStringContainsString('<li>Second point</li>', $result);
    $this->assertStringContainsString('<li>Third point</li>', $result);
    $this->assertStringEndsWith('</ul>', $result);
  }

  /**
   * @covers ::generate
   */
  public function testGenerateUsesEntityTranslation(): void {
    $translationLanguage = $this->prophesize(LanguageInterface::class);
    $translationLanguage->getId()->willReturn('sv');
    $translationLanguage->getName()->willReturn('Swedish');

    $translation = $this->prophesize(ContentEntityInterface::class);
    $translation->hasTranslation('sv')->willReturn(FALSE);
    $translation->language()->willReturn($translationLanguage->reveal());
    $translation->getEntityTypeId()->willReturn('node');
    $translation->id()->willReturn('1');

    $originalEntity = $this->prophesize(ContentEntityInterface::class);
    $originalEntity->hasTranslation('sv')->willReturn(TRUE);
    $originalEntity->getTranslation('sv')->willReturn($translation->reveal());

    $textConverter = $this->prophesize(TextConverterManager::class);
    $textConverter->convert($translation->reveal())->willReturn('Swedish page content.');

    $prompt = $this->prophesize(AiPromptInterface::class);
    $prompt->getPrompt()->willReturn('Summarize {content} in {language}.');

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load('helfi_content_summary__helfi_content_summary_default')->willReturn($prompt->reveal());

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('ai_prompt')->willReturn($storage->reveal());

    $chatMessage = new ChatMessage('assistant', 'Swedish summary');
    $chatOutput = new ChatOutput($chatMessage, '', []);

    $chatProvider = $this->prophesize(ChatInterface::class);
    $chatProvider->chat(Argument::type(ChatInput::class), Argument::type('string'))->willReturn($chatOutput);

    $aiProvider = $this->prophesize(AiChatProviderInterface::class);
    $aiProvider->getSetProvider('chat')->willReturn([
      'provider_id' => $chatProvider->reveal(),
      'model_id' => 'gpt-4o',
    ]);

    $generator = new AiSummaryGenerator(
      $aiProvider->reveal(),
      $entityTypeManager->reveal(),
      $textConverter->reveal(),
      $this->logger,
    );

    $result = $generator->generate($originalEntity->reveal(), 'sv');
    $this->assertNotNull($result);
    $this->assertStringContainsString('Swedish summary', $result);
  }

  /**
   * @covers ::generate
   */
  public function testGenerateReturnsEmptyStringWhenAiReturnsBlankLines(): void {
    $textConverter = $this->prophesize(TextConverterManager::class);
    $textConverter->convert($this->entity)->willReturn('Page content here.');

    $prompt = $this->prophesize(AiPromptInterface::class);
    $prompt->getPrompt()->willReturn('Summarize {content} in {language}.');

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load('helfi_content_summary__helfi_content_summary_default')->willReturn($prompt->reveal());

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('ai_prompt')->willReturn($storage->reveal());

    $chatMessage = new ChatMessage('assistant', "\n\n\n");
    $chatOutput = new ChatOutput($chatMessage, '', []);

    $chatProvider = $this->prophesize(ChatInterface::class);
    $chatProvider->chat(Argument::type(ChatInput::class), Argument::type('string'))->willReturn($chatOutput);

    $aiProvider = $this->prophesize(AiChatProviderInterface::class);
    $aiProvider->getSetProvider('chat')->willReturn([
      'provider_id' => $chatProvider->reveal(),
      'model_id' => 'gpt-4o',
    ]);

    $generator = new AiSummaryGenerator(
      $aiProvider->reveal(),
      $entityTypeManager->reveal(),
      $textConverter->reveal(),
      $this->logger,
    );

    $result = $generator->generate($this->entity, 'fi');
    $this->assertSame('', $result);
  }

}
