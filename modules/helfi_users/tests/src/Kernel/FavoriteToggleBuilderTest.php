<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\helfi_users\FavoriteStorage;
use Drupal\helfi_users\FavoriteToggleBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests FavoriteToggleBuilder.
 */
#[CoversClass(FavoriteToggleBuilder::class)]
#[Group('helfi_users')]
class FavoriteToggleBuilderTest extends FavoriteKernelTestBase {

  /**
   * The favorite toggle builder.
   *
   * @var \Drupal\helfi_users\FavoriteToggleBuilder
   */
  private FavoriteToggleBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->builder = $this->container->get(FavoriteToggleBuilder::class);
  }

  /**
   * Tests the access.
   */
  public function testAccess(): void {
    $node = $this->createPage();

    $this->assertTrue($this->builder->access($this->createEditor(), $node, 'en')->isAllowed(), 'An editor has access.');
    $this->assertFalse($this->builder->access($this->createUser(['access content', 'edit any page content']), $node, 'en')->isAllowed(), 'Access requires the favorites permission.');
    $this->assertFalse($this->builder->access($this->createUser(['access content', 'use favorites']), $node, 'en')->isAllowed(), 'Access requires update access.');
  }

  /**
   * Tests the button build.
   */
  public function testBuild(): void {
    $user = $this->createEditor();
    $this->setCurrentUser($user);
    $node = $this->createPage();
    $translation = $this->addFinnishTranslation($node);

    $build = $this->builder->build($translation, 'icon');
    $this->assertFalse($build['#is_favorite'], 'The button is inactive before adding.');
    $this->assertFalse($build['#show_label'], 'The icon variant hides the label.');
    $this->assertTrue($this->builder->build($translation, 'label')['#show_label'], 'The label variant shows the label.');

    $this->container->get(FavoriteStorage::class)->toggle($user, $translation);
    $this->assertTrue($this->builder->build($translation, 'icon')['#is_favorite'], 'The button of the favorite translation is active.');
    $this->assertFalse($this->builder->build($node, 'icon')['#is_favorite'], 'The button of another translation is inactive.');

    $attributes = $build['#attributes'];
    $post = (string) $attributes['data-hx-post'];
    $this->assertStringContainsString('/admin/favorite/node/' . $node->id() . '/fi/icon?token=', $post, 'The button posts to the toggle route.');
    $this->assertEquals('none', (string) $attributes['data-hx-params'], 'Form values are not posted.');
    $this->assertEquals('outerHTML ignoreTitle:true', (string) $attributes['data-hx-swap'], 'The button swaps itself.');

    $placeholders = array_keys($build['#attached']['placeholders'] ?? []);
    $this->assertNotEmpty($placeholders, 'The CSRF token placeholder is attached.');
    $this->assertStringContainsString((string) reset($placeholders), $post, 'The URL has the CSRF token placeholder.');

    $this->assertContains('user', $build['#cache']['contexts'], 'The button varies by user.');
    $this->assertContains('helfi_favorite:' . $user->id(), $build['#cache']['tags'], 'The button has the user favorite tag.');
  }

  /**
   * Tests the lazy builder.
   */
  public function testLazyBuild(): void {
    $user = $this->createEditor();
    $this->setCurrentUser($user);
    $node = $this->createPage();
    $id = (string) $node->id();

    $this->assertEquals('helfi_favorite_toggle', $this->builder->lazyBuild('node', $id, 'en', 'icon')['#theme'] ?? NULL, 'An editor gets the button.');
    $this->assertArrayNotHasKey('#theme', $this->builder->lazyBuild('user', (string) $user->id(), 'en', 'icon'), 'An unsupported entity type renders nothing.');
    $this->assertArrayNotHasKey('#theme', $this->builder->lazyBuild('node', $id, 'fi', 'icon'), 'A missing translation renders nothing.');
    $this->assertArrayNotHasKey('#theme', $this->builder->lazyBuild('node', '999', 'en', 'icon'), 'A missing entity renders nothing.');

    $this->setCurrentUser($this->createUser(['access content']));
    $build = $this->builder->lazyBuild('node', $id, 'en', 'icon');
    $this->assertArrayNotHasKey('#theme', $build, 'A user without access gets nothing.');
    $this->assertContains('user.permissions', $build['#cache']['contexts'], 'The empty result varies by permissions.');
  }

}
