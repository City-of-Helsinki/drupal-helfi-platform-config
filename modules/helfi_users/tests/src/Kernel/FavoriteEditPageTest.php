<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\helfi_users\FavoriteStorage;
use Drupal\helfi_users\Hook\FavoriteHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the favorite toggle on entity edit pages.
 */
#[CoversClass(FavoriteHooks::class)]
#[Group('helfi_users')]
class FavoriteEditPageTest extends FavoriteKernelTestBase {

  /**
   * Runs the page preprocess for the given route.
   *
   * @param string $routeName
   *   The route name.
   * @param array<string, mixed> $parameters
   *   The route parameters.
   *
   * @return array<string, mixed>
   *   The preprocessed page variables.
   */
  private function preprocessPage(string $routeName, array $parameters = []): array {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn($routeName);
    $routeMatch->method('getParameter')
      ->willReturnCallback(fn (string $name): mixed => $parameters[$name] ?? NULL);

    $hooks = new FavoriteHooks(
      $this->container->get(EntityTypeManagerInterface::class),
      $routeMatch,
      $this->container->get(FavoriteStorage::class),
    );
    $variables = [];
    $hooks->preprocessPage($variables);

    return $variables;
  }

  /**
   * Tests that the edit page toggle toggles the edited translation.
   */
  public function testEditPageToggleTogglesFavorite(): void {
    $user = $this->createEditor();
    $this->setCurrentUser($user);
    $node = $this->createPage();
    $translation = $this->addFinnishTranslation($node);
    $storage = $this->container->get(FavoriteStorage::class);

    $variables = $this->preprocessPage('entity.node.edit_form', ['node' => $translation]);
    $html = $this->clickFavoriteToggle((string) $this->container->get('renderer')->renderRoot($variables['favorite_toggle']));
    $this->assertStringContainsString('Added to favorites', $html, 'The swapped toggle has the added label.');
    $this->assertTrue($storage->isFavorite($user, $translation), 'The first click adds the edited translation.');

    $this->clickFavoriteToggle($html);
    $this->assertFalse($storage->isFavorite($user, $translation), 'The second click removes the edited translation.');

    // The add page should not have the favorite toggle.
    $this->assertArrayNotHasKey('favorite_toggle', $this->preprocessPage('node.add'));

    // Only nodes and TPR entity edit pages should have the favorite toggle.
    $user = $this->createEditor();
    $variables = $this->preprocessPage('entity.user.edit_form', ['user' => $user]);
    $this->assertArrayNotHasKey('favorite_toggle', $variables);
  }

}
