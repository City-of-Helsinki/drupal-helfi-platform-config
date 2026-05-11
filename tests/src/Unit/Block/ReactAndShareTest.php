<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Config\Config;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\helfi_platform_config\Plugin\Block\ReactAndShare;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\ReactAndShare
 *
 * @group helfi_platform_config
 */
class ReactAndShareTest extends UnitTestCase {

  use StringTranslationTrait;

  /**
   * The mock language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface|MockObject
   */
  private ConfigurableLanguageManagerInterface|MockObject $languageManager;

  /**
   * The mocked state.
   *
   * @var \Drupal\Core\State\StateInterface|MockObject
   */
  private StateInterface|MockObject $state;

  /**
   * The mocked route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|MockObject
   */
  private RouteMatchInterface|MockObject $routeMatch;

  /**
   * The block instance being tested.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\ReactAndShare
   */
  private ReactAndShare $reactAndShareBlock;

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

    $this->languageManager = $this->createMock(ConfigurableLanguageManagerInterface::class);
    $this->state = $this->createMock(StateInterface::class);
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    $this->stringTranslation = $this->createMock(TranslationInterface::class);

    $this->reactAndShareBlock = new ReactAndShare(
      [],
      'react_and_share',
      ['provider' => 'helfi_platform_config'],
      $this->languageManager,
      $this->state,
      $this->routeMatch,
    );

    $this->reactAndShareBlock->setStringTranslation($this->stringTranslation);
  }

  /**
   * Tests that create() correctly instantiates the block from the container.
   *
   * @covers ::create
   * @covers ::__construct
   */
  public function testCreate(): void {
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnMap([
      ['language_manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->languageManager],
      ['state', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->state],
      ['current_route_match', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->routeMatch],
    ]);

    $block = ReactAndShare::create($container, [], 'react_and_share', ['provider' => 'helfi_platform_config']);
    $this->assertInstanceOf(ReactAndShare::class, $block);
  }

  /**
   * Tests that the block is hidden on the user canonical route.
   *
   * @covers ::build
   */
  public function testBuildReturnsEmptyArrayOnUserCanonicalRoute(): void {
    $this->routeMatch->expects($this->once())
      ->method('getRouteName')
      ->willReturn('entity.user.canonical');

    $this->assertSame([], $this->reactAndShareBlock->build());
  }

  /**
   * Tests that render array has an empty array when no API key is available.
   *
   * @covers ::build
   */
  public function testBuildReturnsEmptyArrayWhenNoApiKey(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('en');

    $this->languageManager->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language);

    // Ensure environment variable is not set.
    putenv('REACT_AND_SHARE_APIKEY_EN');

    $this->assertSame([], $this->reactAndShareBlock->build());
  }

  /**
   * Tests that render array has the data when API key is available.
   *
   * @covers ::build
   */
  public function testBuildReturnsCorrectRenderArray(): void {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('fi');

    $this->languageManager->method('getCurrentLanguage')
      ->with(LanguageInterface::TYPE_CONTENT)
      ->willReturn($language);

    // Set a fake API key environment variable.
    putenv('REACT_AND_SHARE_APIKEY_FI=fake-api-key');

    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->with('name')->willReturn('Test Site');

    $this->languageManager->method('getLanguageConfigOverride')
      ->with('fi', 'system.site')
      ->willReturn($configMock);

    $this->state->method('get')
      ->with('askem.script_monitoring', TRUE)
      ->willReturn(TRUE);

    $expected = [
      'react_and_share' => [
        '#theme' => 'react_and_share',
        '#title' => new TranslatableMarkup('React and Share', string_translation: $this->stringTranslation),
        '#attached' => [
          'library' => ['helfi_platform_config/react_and_share'],
          'drupalSettings' => [
            'reactAndShareApiKey' => 'fake-api-key',
            'siteName' => 'Test Site',
            'askemMonitoringEnabled' => TRUE,
          ],
        ],
      ],
    ];

    $this->assertEquals($expected, $this->reactAndShareBlock->build());
  }

}
