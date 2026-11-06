<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\helfi_users\Hook\UserDashboardHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for UserDashboardHooks.
 */
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

  /**
   * Extra field machine names registered for the user dashboard.
   *
   * @var list<string>
   */
  private const EXTRA_FIELDS = [
    'user_content',
    'user_tpr_units',
    'user_tpr_services',
    'user_tpr_errand_services',
    'tpr_service_channels',
  ];

  /**
   * The current user mock.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private AccountProxyInterface&MockObject $currentUser;

  /**
   * The module handler mock.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private ModuleHandlerInterface&MockObject $moduleHandler;

  /**
   * The route match mock.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private RouteMatchInterface&MockObject $routeMatch;

  /**
   * The redirect destination mock.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private RedirectDestinationInterface&MockObject $redirectDestination;

  /**
   * The hooks under test.
   *
   * @var \Drupal\helfi_users\Hook\UserDashboardHooks
   */
  private UserDashboardHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    $this->redirectDestination = $this->createMock(RedirectDestinationInterface::class);
    $this->hooks = new UserDashboardHooks(
      $this->currentUser,
      $this->moduleHandler,
      $this->routeMatch,
      $this->redirectDestination,
    );
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
   * Tests that dashboard extra fields are registered for user display.
   */
  public function testUserContentExtraFieldInfo(): void {
    $extra = $this->hooks->userContentExtraFieldInfo();

    foreach (self::EXTRA_FIELDS as $name) {
      $this->assertArrayHasKey($name, $extra['user']['user']['display']);
      $this->assertEquals(10, $extra['user']['user']['display'][$name]['weight']);
      $this->assertTrue($extra['user']['user']['display'][$name]['visible']);
    }
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

    foreach (self::EXTRA_FIELDS as $name) {
      $this->assertArrayNotHasKey($name, $build);
    }
  }

  /**
   * Tests that views are skipped when their display components are absent.
   */
  public function testInjectDashboardViewSkipsWhenComponentMissing(): void {
    $this->currentUser->method('id')->willReturn('1');

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('1');
    $account->method('getRoles')->willReturn(['editor']);

    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->expects($this->exactly(count(self::EXTRA_FIELDS)))
      ->method('getComponent')
      ->willReturn(NULL);

    $build = [];
    $this->hooks->injectDashboardView($build, $account, $display);

    foreach (self::EXTRA_FIELDS as $name) {
      $this->assertArrayNotHasKey($name, $build);
    }
  }

  /**
   * Tests that users without editing roles see the access notice.
   *
   * @param list<string> $roles
   *   The user roles.
   */
  #[DataProvider('accessNoticeRolesProvider')]
  public function testInjectDashboardViewShowsAccessNoticeWithoutRoles(array $roles): void {
    $this->currentUser->method('id')->willReturn('2');

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('2');
    $account->method('getRoles')->with(TRUE)->willReturn($roles);

    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->expects($this->never())->method('getComponent');

    $build = [];
    $this->hooks->injectDashboardView($build, $account, $display);

    $this->assertArrayHasKey('access_notice', $build);
    foreach (self::EXTRA_FIELDS as $name) {
      $this->assertArrayNotHasKey($name, $build);
    }
  }

  /**
   * Provides roles that show the access notice.
   *
   * @return array<string, array{list<string>}>
   *   The test cases.
   */
  public static function accessNoticeRolesProvider(): array {
    return [
      'no roles' => [[]],
      'read only' => [['read_only']],
    ];
  }

  /**
   * Tests that the dashboard tabs are hidden when the access notice is shown.
   */
  public function testFieldGroupPreRenderHidesTabsWithAccessNotice(): void {
    $element = [];
    $group = (object) ['group_name' => 'group_my_content'];

    $this->hooks->fieldGroupPreRender($element, $group, ['access_notice' => []]);

    $this->assertFalse($element['#access']);
  }

  /**
   * Tests that the TPR content group is hidden when helfi_tpr is not installed.
   */
  public function testFieldGroupPreRenderHidesTprGroupWithoutModule(): void {
    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('helfi_tpr')
      ->willReturn(FALSE);

    $element = [];
    $group = (object) ['group_name' => 'group_my_tpr_content'];

    $this->hooks->fieldGroupPreRender($element, $group);

    $this->assertFalse($element['#access']);
  }

  /**
   * Tests that the TPR content group stays visible when helfi_tpr is installed.
   */
  public function testFieldGroupPreRenderKeepsTprGroupWithModule(): void {
    $this->moduleHandler->expects($this->once())
      ->method('moduleExists')
      ->with('helfi_tpr')
      ->willReturn(TRUE);

    $element = [];
    $group = (object) ['group_name' => 'group_my_tpr_content'];

    $this->hooks->fieldGroupPreRender($element, $group);

    $this->assertArrayNotHasKey('#access', $element);
  }

  /**
   * Tests that unrelated field groups are left unchanged.
   */
  public function testFieldGroupPreRenderIgnoresOtherGroups(): void {
    $this->moduleHandler->expects($this->never())->method('moduleExists');

    $element = [];
    $group = (object) ['group_name' => 'group_my_pages'];

    $this->hooks->fieldGroupPreRender($element, $group);

    $this->assertArrayNotHasKey('#access', $element);
  }

  /**
   * Tests that bulk forms rebuilt by views AJAX submit to the original page.
   */
  public function testViewsFormAlterSubmitsBulkFormToOriginalPage(): void {
    $this->container->get('router.builder')->rebuild();
    $this->routeMatch->method('getRouteName')->willReturn('views.ajax');
    $this->redirectDestination->method('get')->willReturn('/admin?title=foo');

    $form = [
      '#action' => '/views/ajax?title=foo',
      '#attributes' => ['class' => ['vbo-view-form']],
    ];
    $this->hooks->viewsFormAlter($form, new FormState(), 'views_form_dashboard_your_content_your_content_block');
    $this->assertEquals('/admin?title=foo', $form['#action'], 'The bulk form submits to the original page.');

    $form = ['#action' => '/views/ajax?title=foo'];
    $this->hooks->viewsFormAlter($form, new FormState(), 'views_form_media_library_widget');
    $this->assertEquals('/views/ajax?title=foo', $form['#action'], 'Other views forms keep their action.');
  }

}
