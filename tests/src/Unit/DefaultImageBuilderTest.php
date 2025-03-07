<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
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
   * The module handler mock.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ModuleHandlerInterface|MockObject $moduleHandler;

  /**
   * The language manager mock.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected LanguageManagerInterface|MockObject $languageManager;

  /**
   * The file URL generator mock.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected FileUrlGeneratorInterface|MockObject $fileUrlGenerator;

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

    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);

    $this->defaultImageBuilder = new DefaultImageBuilder(
      $this->moduleHandler,
      $this->languageManager,
      $this->fileUrlGenerator
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
    $module_path = '/modules/custom/helfi_platform_config';

    $module_mock = $this->getMockBuilder(Extension::class)
      ->disableOriginalConstructor()
      ->getMock();
    $module_mock->method('getPath')->willReturn($module_path);

    $this->moduleHandler->expects($this->once())
      ->method('getModule')
      ->with('helfi_platform_config')
      ->willReturn($module_mock);

    $language_mock = $this->createMock(LanguageInterface::class);
    $language_mock->method('getId')->willReturn('fi');

    $this->languageManager->expects($this->once())
      ->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language_mock);

    $expected_uri = 'https://www.test.hel.ninja/modules/custom/helfi_platform_config/fixtures/og-global.png';
    $this->fileUrlGenerator->expects($this->once())
      ->method('generateAbsoluteString')
      ->with("$module_path/fixtures/og-global.png")
      ->willReturn($expected_uri);

    $this->assertSame($expected_uri, $this->defaultImageBuilder->buildUri(NULL));
  }

  /**
   * Test the Swedish version of the default image.
   */
  public function testBuildUriForSwedishLanguage(): void {
    $module_path = '/modules/custom/helfi_platform_config';

    $module_mock = $this->getMockBuilder(Extension::class)
      ->disableOriginalConstructor()
      ->getMock();
    $module_mock->method('getPath')->willReturn($module_path);

    $this->moduleHandler->expects($this->once())
      ->method('getModule')
      ->with('helfi_platform_config')
      ->willReturn($module_mock);

    $language_mock = $this->createMock(LanguageInterface::class);
    $language_mock->method('getId')->willReturn('sv');

    $this->languageManager->expects($this->once())
      ->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language_mock);

    $expected_uri = 'https://www.test.hel.ninja/modules/custom/helfi_platform_config//fixtures/og-global-sv.png';
    $this->fileUrlGenerator->expects($this->once())
      ->method('generateAbsoluteString')
      ->with("$module_path/fixtures/og-global-sv.png")
      ->willReturn($expected_uri);

    $this->assertSame($expected_uri, $this->defaultImageBuilder->buildUri(NULL));
  }

}
