<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_hero\Kernel;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests allowed values for hero design.
 *
 * @group helfi_paragraphs_hero
 */
class HeroDesignAllowedValuesTest extends KernelTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_hero',
    'field',
    'entity',
    'user',
  ];

  /**
   * Test the hero design allowed values function.
   */
  public function testAllowedValues() {
    // Load the required definition and entity mocks.
    $definition = $this->createMock(FieldStorageDefinitionInterface::class);
    $entity = $this->createMock(FieldableEntityInterface::class);

    // Call the allowed designs function.
    $allowed_designs = helfi_paragraphs_hero_design_allowed_values($definition, $entity);

    // Define the expected allowed designs.
    $expected_designs = [
      'without-image-left' => $this->t('Without image, align left'),
      'with-image-right' => $this->t('Image on the right'),
      'with-image-left' => $this->t('Image on the left'),
      'with-image-bottom' => $this->t('Image on the bottom'),
      'diagonal' => $this->t('Diagonal'),
    ];

    // Assert that the allowed values match the expected values.
    $this->assertEquals($expected_designs, $allowed_designs);
  }

}
