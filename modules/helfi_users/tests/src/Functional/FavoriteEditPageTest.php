<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the favorite toggle on entity edit pages.
 */
#[Group('helfi_users')]
#[RunTestsInSeparateProcesses]
class FavoriteEditPageTest extends FavoriteBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Detect the language from the URL prefix.
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', [
        'language-url' => 0,
        'language-selected' => 1,
      ])
      ->save();
  }

  /**
   * Tests that the edit page toggle toggles the edited translation.
   */
  public function testEditPageToggleTogglesFavorite(): void {
    $user = $this->loginEditor();
    $node = $this->drupalCreateNode(['type' => 'page']);
    $node->addTranslation('fi', ['title' => $this->randomMachineName()])->save();
    $editPath = 'fi/node/' . $node->id() . '/edit';
    $assert = $this->assertSession();

    $this->drupalGet($editPath);
    $assert->elementNotExists('css', '.favorite-toggle--active');
    $html = $this->clickFavoriteToggle($assert->elementExists('css', '.favorite-toggle'));
    $this->assertStringContainsString('favorite-toggle--active', $html, 'The first click adds the favorite.');

    $this->drupalGet($editPath);
    $assert->elementExists('css', '.favorite-toggle--active');

    // Verify the English page is not a favorite.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $assert->elementExists('css', '.favorite-toggle');
    $assert->elementNotExists('css', '.favorite-toggle--active');

    // Remove the favorite.
    $this->drupalGet($editPath);
    $html = $this->clickFavoriteToggle($assert->elementExists('css', '.favorite-toggle'));
    $this->assertStringNotContainsString('favorite-toggle--active', $html, 'The second click removes the favorite.');

    $this->drupalGet('node/add/page');
    $assert->elementNotExists('css', '.favorite-toggle');

    $this->drupalGet('user/' . $user->id() . '/edit');
    $assert->elementNotExists('css', '.favorite-toggle');
  }

}
