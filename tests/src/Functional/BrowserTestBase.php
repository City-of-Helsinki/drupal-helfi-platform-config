<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_platform_config\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\BrowserTestBase as CoreBrowserTestBase;

/**
 * Base test class for helfi platform browser tests.
 */
abstract class BrowserTestBase extends CoreBrowserTestBase {

  /**
   * Gets the language for given language code.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The language.
   */
  protected function getLanguage(string $langcode) : LanguageInterface {
    return $this->container->get('language_manager')->getLanguage($langcode);
  }
}
