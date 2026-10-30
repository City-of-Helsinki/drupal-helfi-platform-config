<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\helfi_users\Controller\FavoriteController;
use Drupal\helfi_users\FavoriteStorage;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the favorite toggle route.
 */
#[CoversClass(FavoriteController::class)]
#[Group('helfi_users')]
class FavoriteRouteTest extends FavoriteKernelTestBase {

  /**
   * Sends a request to the toggle route.
   *
   * @param string $path
   *   The route path without a leading slash.
   * @param string $method
   *   The HTTP method.
   * @param bool $withToken
   *   Whether to add a valid CSRF token.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  private function request(string $path, string $method = 'POST', bool $withToken = TRUE): Response {
    $uri = '/' . $path;
    if ($withToken) {
      $uri .= '?token=' . $this->container->get('csrf_token')->get($path);
    }
    $request = Request::create($uri, $method);
    $request->setSession($this->container->get('request_stack')->getCurrentRequest()->getSession());

    return $this->container->get('http_kernel')->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);
  }

  /**
   * Logs in a content editor.
   *
   * @return \Drupal\user\UserInterface
   *   The editor.
   */
  private function loginEditor(): UserInterface {
    $user = $this->createEditor();
    $this->setCurrentUser($user);
    return $user;
  }

  /**
   * Tests that posting with a token toggles the favorite.
   */
  public function testPostTogglesFavorite(): void {
    $user = $this->loginEditor();
    $node = $this->createPage();
    $path = 'admin/favorite/node/' . $node->id() . '/en/icon';

    $response = $this->request($path);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('favorite-toggle--active', (string) $response->getContent(), 'The response has the active button.');
    $this->assertTrue($this->container->get(FavoriteStorage::class)->isFavorite($user, $node), 'The page is a favorite.');
  }

  /**
   * Tests that posting toggles only an existing translation.
   */
  public function testPostTogglesOnlyExistingTranslation(): void {
    $user = $this->loginEditor();
    $node = $this->createPage();
    $translation = $this->addFinnishTranslation($node);

    $this->request('admin/favorite/node/' . $node->id() . '/fi/icon');

    $storage = $this->container->get(FavoriteStorage::class);
    $this->assertTrue($storage->isFavorite($user, $translation), 'The Finnish translation is a favorite.');
    $this->assertFalse($storage->isFavorite($user, $node), 'The English translation is not a favorite.');

    // Deny a page without the Finnish translation.
    $this->expectException(AccessDeniedHttpException::class);
    $this->request('admin/favorite/node/' . $this->createPage()->id() . '/fi/icon');
  }

  /**
   * Tests that invalid requests are denied.
   */
  public function testInvalidRequestsAreDenied(): void {
    $editor = $this->createEditor();
    $node = $this->createPage();
    $path = 'admin/favorite/node/' . $node->id() . '/en/icon';

    $cases = [
      'A GET request is not allowed.' => [$editor, 'GET', TRUE, MethodNotAllowedHttpException::class],
      'A request without a CSRF token is denied.' => [$editor, 'POST', FALSE, AccessDeniedHttpException::class],
      'A user without the favorites permission is denied.' => [$this->createUser(['access content', 'edit any page content']), 'POST', TRUE, AccessDeniedHttpException::class],
      'A user without update access is denied.' => [$this->createUser(['access content', 'use favorites']), 'POST', TRUE, AccessDeniedHttpException::class],
    ];

    foreach ($cases as $message => [$account, $method, $withToken, $exception]) {
      $this->setCurrentUser($account);
      try {
        $this->request($path, $method, $withToken);
        $this->fail($message);
      }
      catch (HttpExceptionInterface $e) {
        $this->assertInstanceOf($exception, $e, $message);
      }
    }
  }

  /**
   * Tests that invalid paths are not found.
   */
  public function testInvalidPathsAreNotFound(): void {
    $user = $this->loginEditor();
    $node = $this->createPage();

    $paths = [
      'An unsupported entity type is not found.' => 'admin/favorite/user/' . $user->id() . '/en/icon',
      'An unknown display variant is not found.' => 'admin/favorite/node/' . $node->id() . '/en/unknown',
    ];

    foreach ($paths as $message => $path) {
      try {
        $this->request($path);
        $this->fail($message);
      }
      catch (NotFoundHttpException) {
        $this->addToAssertionCount(1);
      }
    }
  }

}
