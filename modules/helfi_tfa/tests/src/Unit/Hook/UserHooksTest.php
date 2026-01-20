<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tfa\Unit\Hook;

use Drupal\helfi_tfa\Hook\UserHooks;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the user hooks.
 *
 * @group helfi_tfa
 */
final class UserHooksTest extends UnitTestCase {

  /**
   * Tests userLogin() basic behavior with different inputs.
   *
   * @dataProvider providerUserLogin
   */
  public function testUserLogin(
    $testUserConfig,
    $currentUserName,
    $expectedDeleteCount,
  ): void {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->createMock(ImmutableConfig::class);
    $userDataService = $this->createMock(UserDataInterface::class);
    $user = $this->createMock(UserInterface::class);
    $userId = 123;

    $configFactory
      ->method('get')
      ->with('helfi_platform_config.e2e_test_users')
      ->willReturn($config);
    $config
      ->method('get')
      ->with('users')
      ->willReturn($testUserConfig);

    $user
      ->method('getAccountName')
      ->willReturn($currentUserName);
    $user
      ->method('id')
      ->willReturn($userId);

    $userDataService
      ->expects($this->exactly($expectedDeleteCount))
      ->method('delete')
      ->with('tfa', $userId, NULL);

    $sut = new UserHooks(
      $configFactory,
      $userDataService
    );

    $sut->userLogin($user);
  }

  /**
   * Data provider for testUserLogin().
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerUserLogin(): array {
    return [
      'no test user config' => [
        'testUserConfig' => [],
        'currentUserName' => 'testuser',
        'expectedDeleteCount' => 0,
      ],
      'current user is not a test user' => [
        'testUserConfig' => [['username' => 'testuser']],
        'currentUserName' => 'nottestuser',
        'expectedDeleteCount' => 0,
      ],
      'current user is a test user' => [
        'testUserConfig' => [['username' => 'testuser']],
        'currentUserName' => 'testuser',
        'expectedDeleteCount' => 1,
      ],
    ];
  }

}
