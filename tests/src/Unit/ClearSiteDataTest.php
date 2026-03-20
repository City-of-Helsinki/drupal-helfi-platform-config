<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\helfi_platform_config\ClearSiteData;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Unit tests for the Clear-Site-Data header service.
 */
#[CoversClass(ClearSiteData::class)]
#[Group('helfi_platform_config')]
class ClearSiteDataTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Request time used in tests unless overridden.
   */
  private const REQUEST_TIME = 1700000000;

  /**
   * The config factory prophecy (fresh each test).
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Config\ConfigFactoryInterface>
   */
  private ObjectProphecy $configFactoryProphecy;

  /**
   * The request time service prophecy (fresh each test).
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Component\Datetime\TimeInterface>
   */
  private ObjectProphecy $timeProphecy;

  /**
   * The editable config prophecy for enable()/disable() (fresh each test).
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Config\Config>
   */
  private ObjectProphecy $editableConfigProphecy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->configFactoryProphecy = $this->prophesize(ConfigFactoryInterface::class);
    $this->timeProphecy = $this->prophesize(TimeInterface::class);
    $this->timeProphecy->getRequestTime()->willReturn(self::REQUEST_TIME);
    $this->editableConfigProphecy = $this->prophesize(Config::class);
  }

  /**
   * Builds the service under test from the current prophecies.
   */
  private function sut(): ClearSiteData {
    return new ClearSiteData(
      $this->configFactoryProphecy->reveal(),
      $this->timeProphecy->reveal(),
    );
  }

  /**
   * Maps config keys to return values on an immutable config double.
   *
   * @param array<string, mixed> $values
   *   Keys are config property names.
   */
  private function immutableFromValues(array $values): ImmutableConfig {
    $config = $this->prophesize(ImmutableConfig::class);
    $config->get(Argument::any())->will(function (array $args) use ($values) {
      $key = $args[0];
      return $values[$key] ?? NULL;
    });
    return $config->reveal();
  }

  /**
   * Configures get(helfi_platform_config.clear_site_data) to return the map.
   *
   * @param array<string, mixed> $values
   *   Keys are config property names.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The revealed immutable config (for assertSame etc.).
   */
  private function givenImmutableRead(array $values): ImmutableConfig {
    $immutable = $this->immutableFromValues($values);
    $this->configFactoryProphecy->get(ClearSiteData::CONFIG_NAME)->willReturn($immutable);
    return $immutable;
  }

  /**
   * Wires getEditable() to the shared editable config prophecy.
   */
  private function givenEditableFromFactory(): void {
    $this->configFactoryProphecy
      ->getEditable(ClearSiteData::CONFIG_NAME)
      ->willReturn($this->editableConfigProphecy->reveal());
  }

  /**
   * Expects enable() to fail validation before mutating editable config.
   */
  private function givenEnableWillFailBeforePersist(): void {
    $this->editableConfigProphecy->set(Argument::cetera())->shouldNotBeCalled();
    $this->editableConfigProphecy->save()->shouldNotBeCalled();
    $this->givenEditableFromFactory();
  }

  /**
   * Tests isEnabled() for configuration states that must return FALSE.
   *
   * @param array<string, mixed> $configValues
   *   Mock config key-value pairs for helfi_platform_config.clear_site_data.
   */
  #[DataProvider('providerIsEnabledFalse')]
  public function testIsEnabledReturnsFalse(array $configValues): void {
    $this->givenImmutableRead($configValues);
    $this->assertFalse($this->sut()->isEnabled());
  }

  /**
   * Data provider for disabled states.
   */
  public static function providerIsEnabledFalse(): array {
    return [
      'disabled flag' => [
        [
          'enable' => FALSE,
          'directives' => ['cache'],
          'expire_after' => self::REQUEST_TIME + 3600,
        ],
      ],
      'empty directives' => [
        [
          'enable' => TRUE,
          'directives' => [],
          'expire_after' => self::REQUEST_TIME + 3600,
        ],
      ],
      'null directives' => [
        [
          'enable' => TRUE,
          'directives' => NULL,
          'expire_after' => self::REQUEST_TIME + 3600,
        ],
      ],
      'null expire' => [
        [
          'enable' => TRUE,
          'directives' => ['cache'],
          'expire_after' => NULL,
        ],
      ],
      'zero expire treated as disabled' => [
        [
          'enable' => TRUE,
          'directives' => ['cache'],
          'expire_after' => 0,
        ],
      ],
      'expired' => [
        [
          'enable' => TRUE,
          'directives' => ['cache'],
          'expire_after' => self::REQUEST_TIME - 1,
        ],
      ],
    ];
  }

  /**
   * Tests isEnabled() when the feature is active and not expired.
   */
  public function testIsEnabledReturnsTrueWhenConfiguredAndNotExpired(): void {
    $this->givenImmutableRead([
      'enable' => TRUE,
      'directives' => ['cache', 'cookies'],
      'expire_after' => self::REQUEST_TIME + 3600,
    ]);
    $this->assertTrue($this->sut()->isEnabled());
  }

  /**
   * Tests enable() rejects unknown directives.
   */
  public function testEnableThrowsWhenNoValidDirectives(): void {
    $this->givenEnableWillFailBeforePersist();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid Clear-Site-Data directives');
    $this->sut()->enable(['not-a-real-directive']);
  }

  /**
   * Tests enable() rejects expire time below the minimum.
   */
  public function testEnableThrowsWhenExpireBelowMinimum(): void {
    $this->givenEnableWillFailBeforePersist();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid Clear-Site-Data expire time');
    $this->sut()->enable(['cache'], 0);
  }

  /**
   * Tests enable() rejects expire time above the maximum.
   */
  public function testEnableThrowsWhenExpireAboveMaximum(): void {
    $this->givenEnableWillFailBeforePersist();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid Clear-Site-Data expire time');
    $this->sut()->enable(['cache'], ClearSiteData::MAX_EXPIRE_TIME + 1);
  }

  /**
   * Tests enable() persists enable flag, directives, and computed expiry.
   */
  public function testEnableSavesExpectedValues(): void {
    $this->editableConfigProphecy->set('enable', TRUE)->shouldBeCalled();
    $this->editableConfigProphecy->set('directives', ['cookies'])->shouldBeCalled();
    $this->editableConfigProphecy->set('expire_after', self::REQUEST_TIME + 2 * 3600)->shouldBeCalled();
    $this->editableConfigProphecy->save()->shouldBeCalled();
    $this->givenEditableFromFactory();
    $this->sut()->enable(['cookies'], 2);
  }

  /**
   * Directive values must match VALID_DIRECTIVES exactly.
   */
  public function testEnableThrowsWhenDirectivesHaveSurroundingWhitespace(): void {
    $this->givenEnableWillFailBeforePersist();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid Clear-Site-Data directives');
    $this->sut()->enable(['  cookies  ']);
  }

  /**
   * Tests enable() stores only the wildcard when '*' is among directives.
   */
  public function testEnableCollapsesToWildcardWhenStarPresent(): void {
    $this->editableConfigProphecy->set('enable', TRUE)->shouldBeCalled();
    $this->editableConfigProphecy->set('directives', ['*'])->shouldBeCalled();
    $this->editableConfigProphecy->set('expire_after', self::REQUEST_TIME + 3600)->shouldBeCalled();
    $this->editableConfigProphecy->save()->shouldBeCalled();
    $this->givenEditableFromFactory();
    $this->sut()->enable(['cache', '*']);
  }

  /**
   * Tests disable() clears stored settings.
   */
  public function testDisableClearsSettings(): void {
    $this->editableConfigProphecy->set('enable', FALSE)->shouldBeCalled();
    $this->editableConfigProphecy->set('directives', NULL)->shouldBeCalled();
    $this->editableConfigProphecy->set('expire_after', NULL)->shouldBeCalled();
    $this->editableConfigProphecy->save()->shouldBeCalled();
    $this->givenEditableFromFactory();
    $this->sut()->disable();
  }

  /**
   * Tests getActiveDirectives() returns the directives from config.
   */
  public function testGetActiveDirectivesReturnsConfigValue(): void {
    $expected = ['storage'];
    $this->givenImmutableRead(['directives' => $expected]);
    $this->assertSame($expected, $this->sut()->getActiveDirectives());
  }

  /**
   * Tests getActiveExpireAfter() returns the expiry timestamp from config.
   */
  public function testGetActiveExpireAfterReturnsConfigValue(): void {
    $expected = self::REQUEST_TIME + 100;
    $this->givenImmutableRead(['expire_after' => $expected]);
    $this->assertSame($expected, $this->sut()->getActiveExpireAfter());
  }

  /**
   * Tests getDependencyMetadata() returns the immutable config object.
   */
  public function testGetDependencyMetadataReturnsConfigObject(): void {
    $immutable = $this->givenImmutableRead([]);
    $this->assertSame($immutable, $this->sut()->getDependencyMetadata());
  }

  /**
   * Tests disableIfExpired() does not persist when the header is not enabled.
   */
  public function testDisableIfExpiredDoesNothingWhenNotEnabled(): void {
    $this->givenImmutableRead([
      'enable' => FALSE,
      'directives' => ['cache'],
      'expire_after' => self::REQUEST_TIME + 3600,
    ]);
    $this->configFactoryProphecy->getEditable(Argument::any())->shouldNotBeCalled();
    $this->sut()->disableIfExpired();
  }

  /**
   * Tests disableIfExpired() does not call disable when still enabled.
   */
  public function testDisableIfExpiredDoesNotCallDisableWhenEnabled(): void {
    $this->givenImmutableRead([
      'enable' => TRUE,
      'directives' => ['cache'],
      'expire_after' => self::REQUEST_TIME + 3600,
    ]);
    $this->configFactoryProphecy->getEditable(Argument::any())->shouldNotBeCalled();
    $this->sut()->disableIfExpired();
  }

  /**
   * Tests disableIfExpired() persists disable when expiry is in the past.
   */
  public function testDisableIfExpiredCallsDisableWhenExpired(): void {
    $this->givenImmutableRead([
      'enable' => TRUE,
      'directives' => ['cache'],
      'expire_after' => self::REQUEST_TIME - 3600,
    ]);
    $this->editableConfigProphecy->set('enable', FALSE)->shouldBeCalled();
    $this->editableConfigProphecy->set('directives', NULL)->shouldBeCalled();
    $this->editableConfigProphecy->set('expire_after', NULL)->shouldBeCalled();
    $this->editableConfigProphecy->save()->shouldBeCalled();
    $this->givenEditableFromFactory();
    $this->sut()->disableIfExpired();
  }

}
