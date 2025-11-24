<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\helfi_platform_config\Plugin\Block\ProfileBlock;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\helfi_platform_config\Plugin\Block\ProfileBlock
 *
 * @group helfi_platform_config
 */
class ProfileBlockTest extends UnitTestCase {

  /**
   * The mock user account.
   *
   * @var \Drupal\Core\Session\AccountInterface|MockObject
   */
  private AccountInterface|MockObject $currentUser;

  /**
   * The profile block instance.
   *
   * @var \Drupal\helfi_platform_config\Plugin\Block\ProfileBlock
   */
  private ProfileBlock $profileBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->currentUser = $this->createMock(AccountInterface::class);

    $this->profileBlock = new ProfileBlock(
      [],
      'profile_block',
      ['provider' => 'helfi_platform_config'],
      $this->currentUser
    );
  }

  /**
   * Tests that getCacheContexts() returns the expected cache contexts.
   *
   * @covers ::getCacheContexts
   */
  public function testGetCacheContexts(): void {
    $expectedContexts = Cache::mergeContexts(['user'], []);
    $this->assertSame($expectedContexts, $this->profileBlock->getCacheContexts());
  }

  /**
   * Tests that render array returns the anonymous user correctly.
   *
   * @covers ::build
   */
  public function testBuildForAnonymousUser(): void {
    $this->currentUser->expects($this->once())
      ->method('isAuthenticated')
      ->willReturn(FALSE);

    $expected = [
      '#theme' => 'profile_block',
      '#logged_in' => FALSE,
      '#url' => Url::fromRoute('user.login'),
    ];

    $this->assertEquals($expected, $this->profileBlock->build());
  }

  /**
   * Tests that render array returns the authenticated user correctly.
   *
   * @covers ::build
   */
  public function testBuildForAuthenticatedUser(): void {
    $this->currentUser->expects($this->once())
      ->method('isAuthenticated')
      ->willReturn(TRUE);

    $this->currentUser->expects($this->any())
      ->method('getDisplayName')
      ->willReturn('Erkki Esimerkki');

    $this->currentUser->expects($this->once())
      ->method('getEmail')
      ->willReturn('erkki.esimerkki@hel.ninja');

    $expected = [
      '#theme' => 'profile_block',
      '#logged_in' => TRUE,
      '#display_name' => 'Erkki',
      '#full_name' => 'Erkki Esimerkki',
      '#email' => 'erkki.esimerkki@hel.ninja',
      '#url' => Url::fromRoute('user.logout'),
    ];

    $this->assertEquals($expected, $this->profileBlock->build());
  }

  /**
   * Tests that create() correctly initializes the ProfileBlock instance.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
      ->method('get')
      ->with('current_user')
      ->willReturn($this->currentUser);

    $instance = ProfileBlock::create(
      $container,
      [],
      'profile_block',
      ['provider' => 'helfi_platform_config']
    );

    $this->assertInstanceOf(ProfileBlock::class, $instance);
  }

}
