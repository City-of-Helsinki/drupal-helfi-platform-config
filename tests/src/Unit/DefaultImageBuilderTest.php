<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_platform_config\Token\DefaultImageBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the DefaultImageBuilder.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config\Token\DefaultImageBuilder
 * @group helfi_platform_config
 */
class DefaultImageBuilderTest extends UnitTestCase {

  /**
   * The language manager mock.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LanguageManagerInterface|MockObject $languageManager;

  /**
   * The tested service.
   *
   * @var \Drupal\helfi_platform_config\Token\DefaultImageBuilder
   */
  protected DefaultImageBuilder $defaultImageBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->languageManager = $this->createMock(LanguageManagerInterface::class);

    $this->defaultImageBuilder = new DefaultImageBuilder(
      [
        'default' => 'modules/custom/helfi_platform_config/fixtures/og-global.png',
        'sv' => 'modules/custom/helfi_platform_config/fixtures/og-global-sv.png',
      ],
      $this->languageManager,
    );
  }

  /**
   * @covers ::applies
   */
  public function testAppliesAlwaysReturnsTrue(): void {
    $this->assertTrue($this->defaultImageBuilder->applies(NULL));
  }

  /**
   * @covers ::buildUri
   */
  public function testBuildUriReturnsCorrectUri(): void {
    $module_path = 'modules/custom/helfi_platform_config';

    $language_mock = $this->createMock(LanguageInterface::class);
    $language_mock->method('getId')->willReturn('fi');

    $this->languageManager->expects($this->once())
      ->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language_mock);

    $this->assertSame("$module_path/fixtures/og-global.png", $this->defaultImageBuilder->buildUri(NULL));
  }

  /**
   * Test the Swedish version of the default image.
   */
  public function testBuildUriForSwedishLanguage(): void {
    $module_path = 'modules/custom/helfi_platform_config';

    $language_mock = $this->createMock(LanguageInterface::class);
    $language_mock->method('getId')->willReturn('sv');

    $this->languageManager->expects($this->once())
      ->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language_mock);

    $this->assertSame("$module_path/fixtures/og-global-sv.png", $this->defaultImageBuilder->buildUri(NULL));
  }

}
