<?php

namespace Drupal\helfi_test_content\Helper;

use Drupal\Core\Messenger\Messenger;
use Drupal\default_content\Commands\DefaultContentCommands;
use Drupal\default_content\ExporterInterface;
use Drupal\default_content\ImporterInterface;

/**
 * Provides a default content importer.
 */
class TestContentImport extends DefaultContentCommands {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected Messenger $messenger;

  /**
   * Creates a new EntityVersionMatcher instance.
   *
   * @param \Drupal\default_content\ExporterInterface $default_content_exporter
   *   The default content exporter.
   * @param \Drupal\default_content\ImporterInterface $default_content_importer
   *   The default content importer.
   * @param array[] $installed_modules
   *   Installed modules list from the 'container.modules' container parameter.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   */
  public function __construct(
    ExporterInterface $default_content_exporter,
    ImporterInterface $default_content_importer,
    array $installed_modules,
    Messenger $messenger,
  ) {
    parent::__construct($default_content_exporter, $default_content_importer, $installed_modules);
    $this->messenger = $messenger;
  }

  /**
   * Imports default content from given modules.
   *
   * @param array $extensions
   *   An array of modules from which to import the test content.
   */
  public function importContent(array $extensions): void {
    $count = 0;
    $import_from_extensions = [];
    foreach ($this->checkExtensions($extensions) as $extension) {
      if ($extension_count = count($this->defaultContentImporter->importContent($extension, TRUE))) {
        $import_from_extensions[] = $extension;
        $count += $extension_count;
      }
    }
    if ($count) {
      $extensions = implode(', ', $import_from_extensions);
      $this->messenger->addStatus("Imported default content from $extensions.");
    }
  }

}
