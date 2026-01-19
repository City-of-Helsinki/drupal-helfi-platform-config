<?php

declare(strict_types=1);

namespace Drupal\helfi_tfa\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\tfa\TfaUserDataTrait;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * User hook implementations for TFA.
 */
class UserHooks {

  use TfaUserDataTrait;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly UserDataInterface $userDataService,
  ) {
  }

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function userLogin(UserInterface $user): void {
    $e2eTestUsers = $this->configFactory->get('helfi_platform_config.e2e_test_users')->get('users');
    if (empty($e2eTestUsers)) {
      return;
    }

    $e2eTestUsers = array_column($e2eTestUsers, 'username');
    $username = $user->getAccountName();

    // Clear TFA data for E2E test users to allow repeated login without
    // having to activate TFA.
    if ($username && in_array($username, $e2eTestUsers)) {
      $this->deleteUserData('tfa', NULL, $user->id(), $this->userDataService);
    }
  }

}
