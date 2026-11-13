<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\helfi_platform_config\Plugin\Block\OutdatedBrowserBlock;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\OutdatedBrowserBlock
 *
 * @group helfi_platform_config
 */
class OutdatedBrowserBlockTest extends UnitTestCase {

  /**
   * The block instance being tested.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\OutdatedBrowserBlock
   */
  private OutdatedBrowserBlock $block;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->block = new OutdatedBrowserBlock(
      [],
      'outdated_browser',
      ['provider' => 'helfi_platform_config'],
    );
    $this->block->setStringTranslation($this->createMock(TranslationInterface::class));
  }

  /**
   * Tests that the render array has the theme, texts and library.
   *
   * @covers ::build
   */
  public function testBuild(): void {
    $build = $this->block->build();

    $this->assertSame('outdated_browser', $build['#theme']);
    $this->assertSame(['library' => ['helfi_platform_config/outdated_browser']], $build['#attached']);

    $texts = [
      '#title' => 'Your browser is out of date',
      '#body' => 'The browser you are using is outdated and might pose a security risk when you visit other websites. Update your browser to the latest available version.',
      '#type_label' => 'Attention',
      '#close_label' => 'Close',
    ];

    foreach ($texts as $key => $text) {
      $this->assertInstanceOf(TranslatableMarkup::class, $build[$key]);
      $this->assertSame($text, $build[$key]->getUntranslatedString());
      // The translations are in the "Outdated browser" context.
      $this->assertSame('Outdated browser', $build[$key]->getOption('context'));
    }
  }

}
