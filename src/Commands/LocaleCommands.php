<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Commands;

use Drupal\Component\Gettext\PoStreamReader;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
class LocaleCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected TranslationInterface $translationManager;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translationManager
   *   The translation manager.
   */
  public function __construct(
    LanguageManagerInterface $languageManager,
    FileSystemInterface $fileSystem,
    TranslationManager $translationManager
  ) {
    $this->languageManager = $languageManager;
    $this->fileSystem = $fileSystem;
    $this->translationManager = $translationManager;
  }

  /**
   * Gets the translation files.
   *
   * @param string $language
   *   The langcode.
   * @param string $module
   *   The module name.
   *
   * @return \stdClass[]
   *   Translation file objects.
   */
  private function getTranslationFiles(string $language, string $module) : array {
    $basePath = drupal_get_path('module', $module);
    $dir = sprintf('%s/translations/%s', $basePath, $language);

    if (!is_dir($dir)) {
      return [];
    }

    $uris = [];
    foreach ($this->fileSystem->scanDirectory($dir, '/\.po/') as $file) {
      $uris[] = $file;
    }
    return $uris;
  }

  /**
   * Creates a PO stream reader instance.
   *
   * @param string $langcode
   *   The langcode.
   * @param object $file
   *   The file.
   *
   * @return \Drupal\Component\Gettext\PoStreamReader
   *   The stream reader.
   */
  private function createStreamReader(string $langcode, object $file) : PoStreamReader {
    $reader = new PoStreamReader();
    $reader->setLangcode($langcode);
    $reader->setURI($file->uri);
    $reader->open();
    return $reader;
  }

  /**
   * Pre-command hook to import english source strings.
   *
   * @param string $module
   *   The module name.
   *
   * @command helfi:locale-import
   */
  public function import(string $module) {
    foreach ($this->languageManager->getLanguages() as $language) {
      // Skip default language.
      if ($language->isDefault()) {
        continue;
      }

      foreach ($this->getTranslationFiles($language->getId(), $module) as $file) {
        // Expose source strings (to make them translatable).
        $reader = $this->createStreamReader($language->getId(), $file);

        while ($item = $reader->readItem()) {
          $options = [
            'langcode' => $language->getId(),
          ];

          if ($context = $item->getContext()) {
            $options['context'] = $context;
          }
          $this->translationManager
            ->translateString(new TranslatableMarkup($item->getSource(), [], $options));
        }
        $process = $this->processManager()->process([
          'drush',
          'locale:import',
          $language->getId(),
          $file->uri,
        ]);
        $process->run(function ($type, $output) {
          $this->io()->write($output);
        });
      }
    }
  }

}
