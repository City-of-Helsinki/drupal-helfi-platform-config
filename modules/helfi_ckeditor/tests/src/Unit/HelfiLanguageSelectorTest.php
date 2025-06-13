<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ckeditor\Unit;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\editor\Entity\Editor;
use Drupal\helfi_ckeditor\Plugin\CKEditor5Plugin\HelfiLanguageSelector;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\helfi_ckeditor\Plugin\CKEditor5Plugin\HelfiLanguageSelector
 */
final class HelfiLanguageSelectorTest extends TestCase {

  /**
   * Tests that getDynamicPluginConfig returns valid languages.
   *
   * @covers ::create
   * @covers ::getDynamicPluginConfig
   * @covers ::getLanguages
   */
  public function testGetDynamicPluginConfigReturnsValidLanguages(): void {
    $mockLangManager = $this->createMock(LanguageManager::class);
    $mockLangManager->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn(new Language([
        'id' => 'fi',
      ]));

    $plugin = new HelfiLanguageSelector([], 'helfiLanguageSelector', [], $mockLangManager);

    $editor = $this->createMock(Editor::class);
    $result = $plugin->getDynamicPluginConfig([], $editor);

    $this->assertArrayHasKey('helfiLanguageSelector', $result);
    $config = $result['helfiLanguageSelector'];

    $this->assertArrayHasKey('language_list', $config);
    $this->assertArrayHasKey('current_language', $config);
    $this->assertEquals('fi', $config['current_language']);

    $languages = $config['language_list'];

    // Assert that the first four languages are fi, sv, en, af (in that order).
    $this->assertEquals('fi', $languages[0]['languageCode']);
    $this->assertEquals('sv', $languages[1]['languageCode']);
    $this->assertEquals('en', $languages[2]['languageCode']);
    $this->assertEquals('af', $languages[3]['languageCode']);

    // Assert that skipped language is not included.
    $skipped = array_column($languages, 'languageCode');
    $this->assertNotContains('pt-br', $skipped);

    // Assert that a known missing language was added.
    $this->assertContains('co', array_column($languages, 'languageCode'));
  }

}
