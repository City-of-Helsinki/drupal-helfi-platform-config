<?php

declare(strict_types=1);

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

  /**
   * Enables the given module(s).
   *
   * @param string|array $module
   *   The module(s) to enable.
   */
  protected function enableModule(string|array $module) : void {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $moduleHandler */
    $moduleHandler = $this->container->get('module_installer');
    $moduleHandler->install(is_array($module) ? $module : [$module]);
  }

  /**
   * Make sure front page works with all languages.
   */
  protected function assertFrontPageLanguages() : void {
    foreach (['fi', 'en', 'sv'] as $langcode) {
      $this->drupalGet('<front>', ['language' => $this->getLanguage($langcode)]);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->responseHeaderEquals('content-language', $langcode);
    }
  }

}
