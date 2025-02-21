<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Tests\Kernel\ConfigUpdate;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdater;
use Drupal\helfi_platform_config\Drush\Commands\ConfigUpdaterCommands;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use Drush\Commands\DrushCommands;

/**
 * Tests ConfigUpdater.
 */
class ConfigUpdaterTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'config_rewrite',
    'user',
    'helfi_platform_config',
    'helfi_user_roles',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['user', 'helfi_user_roles']);
    // Create a new user to make sure we don't accidentally get all
    // permissions later due to user being uid 1.
    $this->createUser();
  }

  /**
   * Resets the installed config.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account.
   */
  private function resetConfig(UserInterface $account): void {
    // Remove role and make sure it's granted again by ConfigUpdater::update().
    Role::load(Role::AUTHENTICATED_ID)->revokePermission('access content')->save();
    $this->assertFalse($account->hasPermission('access content'));

    $this->config('system.site')->set('name', 'Test')->save();
    $this->assertEquals('Test', $this->config('system.site')->get('name'));
  }

  /**
   * Make sure config matches the expected.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account.
   */
  private function assertConfig(UserInterface $account) : void {
    // Make sure config is installed and permissions are granted.
    $this->assertEquals('City of Helsinki', $this->config('system.site')->get('name'));
    $this->assertTrue($account->hasPermission('access content'));
  }

  /**
   * Tests installation config updater update process.
   */
  public function testUpdate() : void {
    $account = $this->createUser();

    // Make sure user has no access to content.
    $this->assertFalse($account->hasPermission('access content'));
    $this->assertEquals('', $this->config('system.site')->get('name'));

    /** @var \Drupal\Core\Extension\ModuleInstallerInterface $installer */
    $installer = $this->container->get(ModuleInstallerInterface::class);
    $installer->install(['helfi_platform_config_update_test']);

    $this->assertConfig($account);

    // Reset config and update it using ConfigUpdater::update().
    $this->resetConfig($account);

    /** @var \Drupal\helfi_platform_config\ConfigUpdate\ConfigUpdater $sut */
    $sut = $this->container->get(ConfigUpdater::class);
    $sut->update('helfi_platform_config_update_test');
    $this->assertConfig($account);

    // Run drush command to make sure it doesn't die to anything.
    $drush = ConfigUpdaterCommands::create($this->container);
    $this->assertEquals(DrushCommands::EXIT_SUCCESS, $drush->update());
    $this->assertConfig($account);
    // Update individual module.
    $this->assertEquals(DrushCommands::EXIT_SUCCESS, $drush->update('helfi_platform_config_update_test'));
  }

}
