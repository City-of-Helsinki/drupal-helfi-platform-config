<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Config;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\helfi_ai\Config\ToneCheckToolbarOverride;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Test the tone check toolbar config override.
 */
#[Group('helfi_ai')]
#[CoversClass(ToneCheckToolbarOverride::class)]
class ToneCheckToolbarOverrideTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Build the override with a mocked config storage.
   *
   * @param bool $enabled
   *   Whether the tone check feature is enabled.
   * @param array<string, array<mixed>> $stored
   *   Stored editor config keyed by config name.
   *
   * @return \Drupal\helfi_ai\Config\ToneCheckToolbarOverride
   *   The override under test.
   */
  private function createOverride(bool $enabled, bool $hasPermission, array $stored = []): ToneCheckToolbarOverride {
    $storage = $this->prophesize(StorageInterface::class);
    $storage->read('helfi_ai.settings')->willReturn(['enable_tone_check' => $enabled]);
    $currentUser = $this->prophesize(AccountProxyInterface::class);
    $currentUser->hasPermission('use helfi ai tone check')->willReturn($hasPermission);

    foreach ($stored as $name => $data) {
      $storage->read($name)->willReturn($data);
    }
    return new ToneCheckToolbarOverride($storage->reveal(), $currentUser->reveal());
  }

  /**
   * Build an editor config array with the toolbar items.
   *
   * @param string[] $items
   *   The toolbar items.
   *
   * @return array<string, mixed>
   *   The editor config.
   */
  private function editor(array $items): array {
    return ['settings' => ['toolbar' => ['items' => $items]]];
  }

  /**
   * Test that the button is inserted.
   */
  public function testButtonInsertedBeforeSourceEditing(): void {
    $override = $this->createOverride(TRUE, TRUE, [
      'editor.editor.full_html' => $this->editor(['bold', '|', 'sourceEditing']),
    ]);
    $overrides = $override->loadOverrides(['editor.editor.full_html']);

    $this->assertSame(
      ['bold', '|', 'aiToneCheck', '|', 'sourceEditing'],
      $overrides['editor.editor.full_html']['settings']['toolbar']['items'],
    );

    $override = $this->createOverride(TRUE, TRUE, [
      'editor.editor.full_html' => $this->editor(['bold', 'aiToneCheck', 'sourceEditing']),
    ]);
    $this->assertSame([], $override->loadOverrides(['editor.editor.full_html']));
  }

  /**
   * Tests that editor overrides depend on the settings config.
   */
  public function testCacheableMetadataTagsSettings(): void {
    $override = $this->createOverride(TRUE, TRUE);
    $this->assertContains(
      'config:helfi_ai.settings',
      $override->getCacheableMetadata('editor.editor.full_html')->getCacheTags(),
    );
  }

}
