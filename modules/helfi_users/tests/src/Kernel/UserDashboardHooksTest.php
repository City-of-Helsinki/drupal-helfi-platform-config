<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\helfi_users\Hook\UserDashboardHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(UserDashboardHooks::class)]
#[Group('helfi_users')]
class UserDashboardHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'helfi_users',
  ];

  private AccountProxyInterface $currentUser;
  private UserDashboardHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->hooks = new UserDashboardHooks($this->currentUser);
    $this->hooks->setStringTranslation($this->container->get('string_translation'));
  }

  /**
   * Tests that views data gets the node authorship filter field.
   */
  public function testNodeAuthorshipFilterAddsField(): void {
    $data = [];
    $this->hooks->nodeAuthorshipFilter($data);

    $this->assertArrayHasKey('helfi_node_authorship', $data['node_field_data']);
    $this->assertEquals('helfi_node_authorship', $data['node_field_data']['helfi_node_authorship']['filter']['id']);
  }

  /**
   * Tests that the user_content extra field is registered for user display.
   */
  public function testUserContentExtraFieldInfo(): void {
    $extra = $this->hooks->userContentExtraFieldInfo();

    $this->assertArrayHasKey('user_content', $extra['user']['user']['display']);
    $this->assertEquals(10, $extra['user']['user']['display']['user_content']['weight']);
  }

  /**
   * Tests that the view is not injected when the viewer is a different user.
   */
  public function testInjectDashboardViewSkipsWhenDifferentUser(): void {
    $this->currentUser->method('id')->willReturn('1');

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('2');

    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->expects($this->never())->method('getComponent');

    $build = [];
    $this->hooks->injectDashboardView($build, $account, $display);

    $this->assertArrayNotHasKey('user_content', $build);
  }

  /**
   * Tests that the view is not injected when the user_content component is absent.
   */
  public function testInjectDashboardViewSkipsWhenComponentMissing(): void {
    $this->currentUser->method('id')->willReturn('1');

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('1');

    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->method('getComponent')->with('user_content')->willReturn(NULL);

    $build = [];
    $this->hooks->injectDashboardView($build, $account, $display);

    $this->assertArrayNotHasKey('user_content', $build);
  }

}
