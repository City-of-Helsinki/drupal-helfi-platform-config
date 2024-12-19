<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_hero\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests allowed values alter for hero design.
 *
 * @group helfi_paragraphs_hero
 */
class HeroDesignAllowedValuesAlterTest extends KernelTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_hero',
    'helfi_paragraphs_hero_test',
    'field',
    'entity',
    'user',
  ];

  /**
   * Test the hero design allowed values alter.
   */
  public function testHeroDesignAlter() {
    // Load the required definition and entity mocks.
    $definition = $this->createMock(FieldStorageDefinitionInterface::class);
    $entity = $this->createMock(FieldableEntityInterface::class);

    // Call the allowed designs function.
    $allowed_designs = helfi_paragraphs_hero_design_allowed_values($definition, $entity);

    // Call the function that triggers the drupal_alter.
    helfi_paragraphs_hero_test_helfi_hero_design_alter($allowed_designs);

    // Define the expected outcome after the alter hook.
    $expected_designs = $allowed_designs + ['test-design' => $this->t('Test design')];

    // Assert that the design data has been altered as expected.
    $this->assertEquals($expected_designs, $allowed_designs);
  }

}
