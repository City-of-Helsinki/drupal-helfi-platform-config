<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit test base for blocks.
 *
 * @group helfi_platform_config
 */
class BlockUnitTestBase extends UnitTestCase {

  use StringTranslationTrait;

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityTypeManagerInterface|MockObject $entityTypeManager;

  /**
   * The entity version matcher mock.
   *
   * @var \Drupal\helfi_platform_config\EntityVersionMatcher|\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityVersionMatcher|MockObject $entityVersionMatcher;

  /**
   * The module handler mock.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ModuleHandlerInterface|MockObject $moduleHandler;

  /**
   * The mocked translator.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stringTranslation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityVersionMatcher = $this->createMock(EntityVersionMatcher::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->stringTranslation = $this->createMock('Drupal\Core\StringTranslation\TranslationInterface');
  }

  /**
   * Helper method to translate strings for tests.
   *
   * @param string $string
   *   The string to translate.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Returns translatable markup.
   */
  protected function translate(string $string): TranslatableMarkup {
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    return new TranslatableMarkup($string, string_translation: $this->stringTranslation);
  }

}
