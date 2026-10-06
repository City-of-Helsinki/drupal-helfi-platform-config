<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManager;
use Drupal\node\NodeInterface;
use Drupal\pathauto\Entity\PathautoPattern;

/**
 * Pathauto alter hooks.
 */
final class PathautoHooks {

  private const string menu_title_pattern = '/[node:menu-link:parents:join-path]/[node:menu-link]';

  public function __construct(protected MenuLinkManager $menuLinkManager) {
  }

  /**
   * Implements hook_pathauto_pattern_alter().
   *
   * If node is page or landing page, use menu title if possible.
   * Defaults to the configured pattern.
   */
  #[Hook('pathauto_pattern_alter')]
  public function pathautoPatternAlter(PathautoPattern &$pattern, array $context): void {
    if (!$context['module'] == 'node' || !reset($context['data']) instanceof NodeInterface) {
      return;
    }

    $node = reset($context['data']);
    if (!in_array($node->bundle(), ['page', 'landing_page'])) {
      return;
    }

    $links = $this->menuLinkManager->loadLinksByRoute(
      'entity.node.canonical',
      ['node' => $node->id()]
    );
    if (!$links) {
      return;
    }

    foreach ($links as $link) {
      $title = $link->getTitle();

      if ($title) {
        $pattern->setPattern(self::menu_title_pattern);
      }
    }
  }

}
