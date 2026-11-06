<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\node\NodeInterface;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests favorites in the user dashboard content listing.
 */
#[Group('helfi_users')]
#[RunTestsInSeparateProcesses]
class FavoriteDashboardTest extends FavoriteBrowserTestBase {

  /**
   * The page node.
   */
  private NodeInterface $node;

  /**
   * The Finnish translation of the page.
   */
  private NodeInterface $translation;

  /**
   * The dashboard path.
   */
  private string $dashboardPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->loginEditor();
    $this->node = $this->drupalCreateNode(['type' => 'page', 'uid' => $user->id()]);
    $this->translation = $this->node->addTranslation('fi', [
      'title' => $this->randomMachineName(),
      'uid' => $user->id(),
    ]);
    $this->translation->save();
    $this->dashboardPath = 'user/' . $user->id();
  }

  /**
   * Gets the listing row of the translation.
   *
   * @param \Drupal\node\NodeInterface $translation
   *   The translation.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The table row.
   */
  private function getRow(NodeInterface $translation): NodeElement {
    return $this->assertSession()->elementExists('xpath', '//tr[contains(., "' . $translation->label() . '")]');
  }

  /**
   * Asserts the favorite state of the translation row.
   *
   * @param \Drupal\node\NodeInterface $translation
   *   The translation.
   * @param bool $isFavorite
   *   The expected favorite state.
   * @param string $message
   *   The assertion message.
   */
  private function assertFavorite(NodeInterface $translation, bool $isFavorite, string $message): void {
    $row = $this->getRow($translation);
    $this->assertSession()->elementExists('css', '.favorite-toggle', $row);
    $this->assertSame($isFavorite, $row->has('css', '.favorite-toggle--active'), $message);
  }

  /**
   * Applies the bulk action to the translations.
   *
   * @param string $action
   *   The action label.
   * @param \Drupal\node\NodeInterface ...$translations
   *   The translations.
   */
  private function applyBulkAction(string $action, NodeInterface ...$translations): void {
    $this->drupalGet($this->dashboardPath);
    foreach ($translations as $translation) {
      $this->assertSession()->elementExists('css', 'input[type="checkbox"]', $this->getRow($translation))->check();
    }
    $value = $this->assertSession()->optionExists('action', $action)->getValue();
    $this->submitForm(['action' => $value], 'Apply to selected items');
  }

  /**
   * Tests that the star in the listing toggles the translation.
   */
  public function testListingToggleTogglesFavorite(): void {
    $this->drupalGet($this->dashboardPath);
    $this->assertFavorite($this->node, FALSE, 'The English row is not a favorite.');
    $this->assertFavorite($this->translation, FALSE, 'The Finnish row is not a favorite.');

    $html = $this->clickFavoriteToggle($this->assertSession()->elementExists('css', '.favorite-toggle', $this->getRow($this->translation)));
    $this->assertStringContainsString('favorite-toggle--active', $html, 'The first click adds the favorite.');

    $this->drupalGet($this->dashboardPath);
    $this->assertFavorite($this->node, FALSE, 'The English row is still not a favorite.');
    $this->assertFavorite($this->translation, TRUE, 'The Finnish row is a favorite.');

    $html = $this->clickFavoriteToggle($this->assertSession()->elementExists('css', '.favorite-toggle', $this->getRow($this->translation)));
    $this->assertStringNotContainsString('favorite-toggle--active', $html, 'The second click removes the favorite.');
  }

  /**
   * Tests that the bulk actions add and remove the selected translations.
   */
  public function testBulkActionsAddAndRemoveFavorites(): void {
    $this->applyBulkAction('Add to favourites', $this->node, $this->translation);
    $this->drupalGet($this->dashboardPath);
    $this->assertFavorite($this->node, TRUE, 'The add action adds the English row.');
    $this->assertFavorite($this->translation, TRUE, 'The add action adds the Finnish row.');

    $this->applyBulkAction('Remove from favourites', $this->translation);
    $this->drupalGet($this->dashboardPath);
    $this->assertFavorite($this->node, TRUE, 'The remove action keeps the unselected English row.');
    $this->assertFavorite($this->translation, FALSE, 'The remove action removes the Finnish row.');
  }

  /**
   * Tests that a favorite added in the content listing is listed.
   */
  public function testContentListingFavoriteIsListed(): void {
    // Add the favorite field to the content listing.
    $view = View::load('content');
    $displays = $view->get('display');
    $displays['default']['display_options']['fields']['helfi_favorite'] = [
      'id' => 'helfi_favorite',
      'table' => 'node_field_data',
      'field' => 'helfi_favorite',
      'entity_type' => 'node',
      'plugin_id' => 'helfi_favorite',
    ];
    $view->set('display', $displays)->save();

    $node = $this->drupalCreateNode([
      'type' => 'page',
      'uid' => $this->drupalCreateUser()->id(),
    ]);

    $this->drupalGet($this->dashboardPath);
    $this->assertSession()->pageTextNotContains((string) $node->label());

    $this->drupalGet('admin/content');
    $html = $this->clickFavoriteToggle($this->assertSession()->elementExists('css', '.favorite-toggle', $this->getRow($node)));
    $this->assertStringContainsString('favorite-toggle--active', $html, 'The content listing click adds the favorite.');

    $this->drupalGet($this->dashboardPath);
    $this->assertFavorite($node, TRUE, 'The favorite of another author is listed.');
  }

}
