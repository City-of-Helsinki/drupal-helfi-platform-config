<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_platform_config\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * Tests locale commands.
 *
 * @group helfi_platform_config
 */
class LocaleCommandsTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'locale',
    'helfi_platform_config_test',
    'helfi_platform_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    foreach (['fi', 'sv'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
  }

  /**
   * Tests helfi:locale-import command.
   */
  public function testImport() {
    $this->drush('helfi:locale-import', ['helfi_platform_config_test']);
    // Invalidate translation cache.
    Cache::invalidateTags(['locale']);

    $strings = [
      'fi' => [
        'Test string' => 'Test string fi',
        'Test string 2' => 'Test string 2 fi',
      ],
      'sv' => [
        'Test string' => 'Test string sv',
      ],
    ];

    foreach ($strings as $langcode => $group) {
      foreach ($group as $source => $expected) {
        $string = new TranslatableMarkup($source, [], ['langcode' => $langcode]);
        $this->assertEquals($expected, (string) $string);
      }
    }

  }

}
