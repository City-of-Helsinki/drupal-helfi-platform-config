<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Translation;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Shows a notice on the edit form of an outdated translation.
 */
class OutdatedTranslationNotice {

  use StringTranslationTrait;

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly OutdatedTranslationChecker $checker,
  ) {
  }

  /**
   * Adds the notice above the node edit form.
   *
   * @param array<string, mixed> $variables
   *   The page variables.
   *
   * @see \Drupal\helfi_platform_config\Hook\PageHooks::preprocessPage()
   */
  public function preprocessPage(array &$variables): void {
    if ($this->routeMatch->getRouteName() !== 'entity.node.edit_form') {
      return;
    }

    $node = $this->routeMatch->getParameter('node');

    if (!$node instanceof NodeInterface) {
      return;
    }

    $days = $this->checker->getOutdatedDays($node);

    if ($days === NULL) {
      return;
    }

    $notice = [
      '#theme' => 'helfi_outdated_translation_notice',
      '#title' => $this->t('Check the language version', [], ['context' => 'Outdated translation']),
      '#message' => $this->t('This language version is @days days older than the Finnish version.', [
        '@days' => $days,
      ], ['context' => 'Outdated translation']),
    ];

    $variables['page']['content'] = ['helfi_outdated_translation_notice' => $notice] + ($variables['page']['content'] ?? []);
  }

}
