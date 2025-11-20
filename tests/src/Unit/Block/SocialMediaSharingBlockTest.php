<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Utility\Token;
use Drupal\helfi_platform_config\Plugin\Block\SocialMediaSharingBlock;
use Drupal\social_media\Event\SocialMediaEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\SocialMediaSharingBlock
 *
 * @group helfi_platform_config
 */
class SocialMediaSharingBlockTest extends UnitTestCase {

  use StringTranslationTrait;

  /**
   * The mock module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|MockObject
   */
  private ModuleHandlerInterface|MockObject $moduleHandler;

  /**
   * The mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|MockObject
   */
  private ConfigFactoryInterface|MockObject $configFactory;

  /**
   * The mock event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|MockObject
   */
  private EventDispatcherInterface|MockObject $eventDispatcher;

  /**
   * The mock file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface|MockObject
   */
  private FileUrlGeneratorInterface|MockObject $fileUrlGenerator;

  /**
   * The mock current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack|MockObject
   */
  private CurrentPathStack|MockObject $currentPath;

  /**
   * The mock token service.
   *
   * @var \Drupal\Core\Utility\Token|\PHPUnit\Framework\MockObject\MockObject
   */
  private Token|MockObject $token;

  /**
   * The block instance being tested.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\SocialMediaSharingBlock
   */
  private SocialMediaSharingBlock $socialMediaSharingBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $this->fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $this->currentPath = $this->createMock(CurrentPathStack::class);
    $this->token = $this->createMock(Token::class);

    $this->token->method('replace')
      ->willReturnCallback(fn($string) => $string);

    $this->socialMediaSharingBlock = new SocialMediaSharingBlock(
      [],
      'helfi_platform_config_social_sharing_block',
      ['provider' => 'helfi_platform_config'],
    );

    // Inject the social media sharing block dependencies using reflection.
    $reflection = new \ReflectionClass($this->socialMediaSharingBlock);
    $property = $reflection->getProperty('token');
    $property->setValue($this->socialMediaSharingBlock, $this->token);

    foreach ([
      'moduleHandler' => $this->moduleHandler,
      'configFactory' => $this->configFactory,
      'eventDispatcher' => $this->eventDispatcher,
      'fileUrlGenerator' => $this->fileUrlGenerator,
      'currentPath' => $this->currentPath,
    ] as $property => $value) {
      $prop = $reflection->getProperty($property);
      $prop->setValue($this->socialMediaSharingBlock, $value);
    }
  }

  /**
   * Tests that render array is empty when the social media module is disabled.
   *
   * @covers ::build
   */
  public function testBuildReturnsEmptyArrayWhenSocialMediaModuleNotEnabled(): void {
    $this->moduleHandler->method('getModule')->with('social_media')->willReturn(NULL);

    $this->assertSame([], $this->socialMediaSharingBlock->build());
  }

  /**
   * Tests that render array is built correctly with valid settings.
   *
   * @covers ::build
   */
  public function testBuildReturnsCorrectRenderArray(): void {
    $this->moduleHandler->method('getModule')->with('social_media')->willReturn(TRUE);

    // Mock configuration for social media settings.
    $configMock = $this->createMock(Config::class);
    $configMock->method('get')->with('social_media')->willReturn([
      'ninja' => [
        'enable' => 1,
        'api_url' => 'https://www.test.hel.ninja/share',
        'api_event' => 'click',
        'text' => 'Share on ninja',
        'img' => 'https://www.test.hel.ninja/ninja-icon.svg',
        'weight' => 1,
      ],
    ]);

    $this->configFactory->method('get')
      ->with('social_media.settings')
      ->willReturn($configMock);

    // Mock path.
    $this->currentPath->method('getPath')->willReturn('/current-page');

    // Mock event dispatcher behavior.
    $this->eventDispatcher->method('dispatch')
      ->willReturnCallback(fn(SocialMediaEvent $event) => $event);

    $expected = [
      'social_sharing_block' => [
        '#theme' => 'social_media_links',
        '#elements' => [
          'ninja' => [
            'text' => 'Share on ninja',
            'api' => new Attribute([
              'click' => 'https://www.test.hel.ninja/share',
            ]),
            'img' => 'https://www.test.hel.ninja/ninja-icon.svg',
          ],
        ],
        '#attached' => [
          'library' => ['social_media/basic'],
          'drupalSettings' => [],
        ],
        '#cache' => [
          'tags' => ['social_media:/current-page'],
          'contexts' => ['url'],
        ],
      ],
    ];

    $this->assertEquals($expected, $this->socialMediaSharingBlock->build());
  }

}
