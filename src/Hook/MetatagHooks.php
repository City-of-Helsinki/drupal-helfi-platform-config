<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\AdminContext;

/**
 * Hook implementations for form alterations.
 */
final readonly class MetatagHooks {

  public function __construct(
    private AdminContext $adminContext,
  ) {
  }

  /**
   * Implements hook_form_alter().
   *
   * The advanced metatag group exposes a large number of tags that editors
   * should not need to touch. Hide everything except the robots "noindex"
   * setting.
   *
   * @phpstan-param array<string, mixed> $form
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // Handle only admin routes.
    if (!$this->adminContext->isAdminRoute()) {
      return;
    }

    // Only act on entity forms that contain the advanced metatag group.
    if (
      !$form_state->getFormObject() instanceof EntityForm ||
      !isset($form['field_metatags']['widget'][0]['advanced'])
    ) {
      return;
    }

    $advanced = &$form['field_metatags']['widget'][0]['advanced'];

    // Hide every advanced tag except robots.
    foreach (Element::children($advanced) as $tag_name) {
      if ($tag_name !== 'robots') {
        $advanced[$tag_name]['#access'] = FALSE;
      }
    }

    // Reduce the robots tag to the single "noindex" checkbox.
    if (isset($advanced['robots'])) {
      $robots = &$advanced['robots'];

      // Hide the extended robots directives.
      $robots['robots-keyed']['#access'] = FALSE;

      // Hide unnecessary options.
      $robots['robots']['#options'] = array_intersect_key(
        $robots['robots']['#options'],
        array_flip(['noindex', 'nofollow'])
      );
    }
  }

}
