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
   * Asserts that paragraph type is enabled for given entity type and field.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param array $paragraphFields
   *   The paragraph fields.
   * @param string $paragraphType
   *   The paragraph type.
   */
  protected function assertParagraphTypeEnabled(string $entityType, string $bundle, array $paragraphFields, string $paragraphType) : void {

    foreach ($paragraphFields as $field) {
      $enabled = $this->getEnabledParagraphTypes($entityType, $bundle, $field);
      $message = vsprintf('Paragraph type (%s) is enabled for field %s in %s (%s)', [
        $paragraphType,
        $field,
        $entityType,
        $bundle,
      ]);
      $this->assertTrue(!empty($enabled[$paragraphType]), $message);
    }
  }

  /**
   * Asserts that paragraph type is not enabled for given entity type and field.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param array $paragraphFields
   *   The paragraph fields.
   * @param string $paragraphType
   *   The paragraph type.
   */
  protected function assertParagraphTypeDisabled(string $entityType, string $bundle, array $paragraphFields, string $paragraphType) : void {

    foreach ($paragraphFields as $field) {
      $enabled = $this->getEnabledParagraphTypes($entityType, $bundle, $field);
      $message = vsprintf('Paragraph type (%s) is not enabled for field %s in %s (%s)', [
        $paragraphType,
        $field,
        $entityType,
        $bundle,
      ]);
      $this->assertTrue(empty($enabled[$paragraphType]), $message);
    }
  }

  /**
   * Checks if the paragraph type is enabled for given entity type and field.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   * @param string $paragraphField
   *   The paragraph field.
   */
  protected function getEnabledParagraphTypes(string $entityType, string $bundle, string $paragraphField) : array {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = $this->container->get('entity_field.manager');
    $entityFieldManager->clearCachedFieldDefinitions();

    $definitions = $entityFieldManager->getFieldDefinitions($entityType, $bundle)[$paragraphField];
    $types = $definitions->getSetting('handler_settings')['target_bundles'];

    return $types;
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
