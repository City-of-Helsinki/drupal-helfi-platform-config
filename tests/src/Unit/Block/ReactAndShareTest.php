<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\Block;

use Drupal\Core\Config\Config;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\Plugin\Block\ReactAndShare;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\ReactAndShare
 *
 * @group helfi_platform_config
 */
class ReactAndShareTest extends UnitTestCase {

  use StringTranslationTrait;

  /**
   * The mock language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface|MockObject
   */
  private ConfigurableLanguageManagerInterface|MockObject $languageManager;

  /**
   * The block instance being tested.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\ReactAndShare
   */
  private ReactAndShare $reactAndShareBlock;

  /**
   * The mocked translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->languageManager = $this->createMock(ConfigurableLanguageManagerInterface::class);
    $this->stringTranslation = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');

    $this->reactAndShareBlock = new ReactAndShare(
      [],
      'react_and_share',
      ['provider' => 'helfi_platform_config']
    );

    // Inject the mock language manager using reflection.
    $reflection = new \ReflectionClass($this->reactAndShareBlock);
    $property = $reflection->getProperty('languageManager');
    $property->setValue($this->reactAndShareBlock, $this->languageManager);

    // Ensure translation works within the block.
    $this->reactAndShareBlock->setStringTranslation($this->createMock(TranslationInterface::class));
  }

  /**
   * Tests that render array has an empty array when no API key is available.
   *
   * @covers ::build
   */
  public function testBuildReturnsEmptyArrayWhenNoApiKey(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');

    $this->languageManager->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language);

    // Ensure environment variable is not set.
    putenv('REACT_AND_SHARE_APIKEY_EN');

    $this->assertSame([], $this->reactAndShareBlock->build());
  }

  /**
   * Tests that render array has the data when API key is available.
   *
   * @covers ::build
   */
  public function testBuildReturnsCorrectRenderArray(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('fi');

    $this->languageManager->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language);

    // Set a fake API key environment variable.
    putenv('REACT_AND_SHARE_APIKEY_FI=fake-api-key');

    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->with('name')->willReturn('Test Site');

    $this->languageManager->method('getLanguageConfigOverride')
      ->with('fi', 'system.site')
      ->willReturn($configMock);

    $expected = [
      'react_and_share' => [
        '#theme' => 'react_and_share',
        '#title' => new TranslatableMarkup('React and Share', string_translation: $this->stringTranslation),
        '#attached' => [
          'library' => ['helfi_platform_config/react_and_share'],
          'drupalSettings' => [
            'reactAndShareApiKey' => 'fake-api-key',
            'siteName' => 'Test Site',
          ],
        ],
      ],
    ];

    $this->assertEquals($expected, $this->reactAndShareBlock->build());
  }

}
