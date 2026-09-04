<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\helfi_platform_config\DTO\ParagraphTypeCollection;
use Drupal\helfi_tpr_config\Hook\TprParagraphHooks;
use Drupal\Tests\helfi_tpr_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests helfi_tpr_config hooks.
 */
#[Group('helfi_tpr_config')]
#[RunTestsInSeparateProcesses]
class TprParagraphHooksTest extends KernelTestBase {

  /**
   * Tests the helfi_paragraph_types hook implementation.
   */
  public function testHookImplementation(): void {
    $types = $this->container->get(ModuleHandlerInterface::class)
      ->invoke('helfi_tpr_config', 'helfi_paragraph_types');

    $this->assertNotEmpty($types);
    $this->assertContainsOnlyInstancesOf(ParagraphTypeCollection::class, $types);
    $this->assertCount(count(TprParagraphHooks::helfiParagraphTypes()), $types);
  }

  /**
   * Tests that the paragraph types are enabled by the hook.
   */
  public function testEnabledParagraphTypes(): void {
    $weights = [];
    foreach (TprParagraphHooks::helfiParagraphTypes() as $type) {
      $key = sprintf('%s:%s:%s:%s', $type->entityType, $type->bundle, $type->field, $type->paragraph);

      // Each entity type, bundle, field and paragraph type is defined once.
      $this->assertArrayNotHasKey($key, $weights, $key . ' is defined more than once');
      $weights[$key] = $type->weight;
    }

    $expected = [
      'tpr_unit:tpr_unit:field_banner:banner' => 0,
      'tpr_unit:tpr_unit:field_content:text' => 0,
      'tpr_unit:tpr_unit:field_content:image_gallery' => 10,
      'tpr_unit:tpr_unit:field_lower_content:list_of_links' => 0,
      'tpr_unit:tpr_unit:field_lower_content:image_gallery' => 14,
      'tpr_service:tpr_service:field_banner:banner' => 0,
      'tpr_service:tpr_service:field_content:unit_contact_card' => 14,
      'tpr_service:tpr_service:field_sidebar_content:sidebar_text' => 1,
      'tpr_service:tpr_service:field_lower_content:image_gallery' => 17,
      'node:page:field_content:unit_search' => 17,
      'node:page:field_lower_content:unit_contact_card' => 18,
      'node:landing_page:field_content:service_list' => 15,
      'paragraphs_library_item:paragraphs_library_item:paragraphs:unit_search' => 0,
    ];

    foreach ($expected as $key => $weight) {
      $this->assertArrayHasKey($key, $weights);
      $this->assertSame($weight, $weights[$key], $key . ' has an unexpected weight');
    }

    // Service list is available only in landing pages.
    $this->assertArrayNotHasKey('node:page:field_content:service_list', $weights);
  }

}
