<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Matches page path pattern fields against the current request.
 */
final class PagePathMatcher {

  /**
   * System route suffixes for client error pages.
   *
   * @var string[]
   */
  private const ERROR_CODES = [
    '401',
    '403',
    '404',
    '4xx',
  ];

  public function __construct(
    private readonly PathMatcherInterface $pathMatcher,
    private readonly AliasManagerInterface $aliasManager,
    private readonly CurrentPathStack $currentPath,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Checks whether the current path matches a page path pattern field.
   *
   * Patterns use the same syntax as the core request path condition: one path
   * per line, "*" as a wildcard, and "<front>" for the front page.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The announcement or survey.
   * @param string $fieldName
   *   The string field that stores the patterns.
   *
   * @return bool
   *   TRUE when the current alias or internal path matches. Never TRUE on a
   *   client error page.
   */
  public function matches(FieldableEntityInterface $entity, string $fieldName): bool {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return FALSE;
    }

    $patterns = mb_strtolower((string) $entity->get($fieldName)->value);
    if ($patterns === '') {
      return FALSE;
    }

    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL || $this->isErrorPage($request)) {
      return FALSE;
    }

    // Resolve the alias before lowercasing. Alias lookup is case-sensitive,
    // while pattern matching follows the request path condition and is not.
    $path = $this->currentPath->getPath($request);
    $path = $path === '/' ? $path : rtrim($path, '/');
    $pathAlias = $this->aliasManager->getAliasByPath($path);
    $pathAlias = $pathAlias === '/' ? $pathAlias : rtrim($pathAlias, '/');

    $path = mb_strtolower($path);
    $pathAlias = mb_strtolower($pathAlias);

    return $this->pathMatcher->matchPath($pathAlias, $patterns)
      || (($path !== $pathAlias) && $this->pathMatcher->matchPath($path, $patterns));
  }

  /**
   * Checks whether the request is rendering a client error page.
   *
   * Drupal keeps the original URL on the error subrequest, so a wildcard
   * would otherwise match the path that was not found.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return bool
   *   TRUE when the request is a 4xx page.
   */
  private function isErrorPage(Request $request): bool {
    // The router leaves _route unset when nothing matches. That original
    // request keeps the missing path, so a wildcard would match it.
    $route = $request->attributes->get('_route');
    if (!is_string($route) || $route === '') {
      return TRUE;
    }

    // Check if we're on a system 4xx route. This covers direct system/4xx calls
    // and errors on other routes when the default Drupal error pages are used.
    $routes = array_map(static fn (string $code): string => 'system.' . $code, self::ERROR_CODES);
    if (in_array($route, $routes, TRUE)) {
      return TRUE;
    }

    // This covers errors on other routes when the default Drupal error pages
    // are not used.
    $status = $request->query->get('_exception_statuscode', $request->request->get('_exception_statuscode'));
    $status = (int) $status;
    return $status >= 400 && $status < 500;
  }

}
