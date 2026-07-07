<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SchemaOrg\Builder;

use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\helfi_platform_config\SchemaOrg\EntityIdTrait;
use Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface;

/**
 * Emits a BreadcrumbList from the active page breadcrumb.
 */
final class BreadcrumbBuilder implements SchemaBuilderInterface {

  use EntityIdTrait;

  public function __construct(
    private readonly ChainBreadcrumbBuilderInterface $breadcrumbManager,
    private readonly RouteMatchInterface $routeMatch,
    private readonly RendererInterface $renderer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(?EntityInterface $entity): bool {
    return $entity instanceof ContentEntityInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    assert($entity instanceof ContentEntityInterface);

    $breadcrumb = $this->breadcrumbManager->build($this->routeMatch);
    $cacheability->addCacheableDependency($breadcrumb);

    $items = [];
    $position = 1;

    foreach ($breadcrumb->getLinks() as $link) {
      $url = $link->getUrl();

      if (!$url->isRouted() && !$url->isExternal()) {
        continue;
      }

      // Link text is usually a string or markup, but can be a render array.
      $text = $link->getText();
      if (is_array($text)) {
        $text = $this->renderer->renderInIsolation($text);
      }

      $items[] = [
        '@type' => 'ListItem',
        'position' => $position++,
        'name' => (string) $text,
        'item' => $url->setAbsolute()->toString(),
      ];
    }

    // A single-item breadcrumb is not useful structured data.
    if (count($items) < 2) {
      return [];
    }

    return [
      [
        '@type' => 'BreadcrumbList',
        '@id' => $this->buildId($entity, 'breadcrumb'),
        'itemListElement' => $items,
      ],
    ];
  }

}
