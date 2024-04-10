<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Functional;

/**
 * Base test class for helfi platform browser tests.
 */
abstract class ParagraphTestBase extends BrowserTestBase {

  /**
   * The paragraph types to test.
   *
   * @return \Drupal\helfi_platform_config\DTO\ParagraphTypeCollection[]
   *   The paragraph types.
   */
  abstract protected function getParagraphTypes(): array;

  /**
   * Asserts that paragraph type is enabled for given entity type and field.
   */
  protected function assertParagraphTypeEnabled() : void {
    foreach ($this->getParagraphTypes() as $type) {
      // $this->enableModule('helfi_paragraphs_' . $type->paragraph);
      $enabled = $this->getEnabledParagraphTypes($type->entityType, $type->bundle, $type->field);
      $message = vsprintf('Paragraph type (%s) is enabled for field %s in %s (%s)', [
        $type->paragraph,
        $type->field,
        $type->entityType,
        $type->bundle,
      ]);
      $this->assertTrue(!empty($enabled[$type->paragraph]), $message);
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
    return $definitions->getSetting('handler_settings')['target_bundles'];
  }

}
