<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\views\ViewExecutable;

/**
 * Render-related hooks for tpr_unit entities.
 */
class TprUnitRenderHooks {

  /**
   * Implements hook_views_post_execute() for the service_units view.
   *
   * @see self::buildDefaultsAlter
   */
  #[Hook('views_post_execute')]
  public function viewsPostExecute(ViewExecutable $view): void {
    if ($view->id() !== 'service_units') {
      return;
    }
    if ((int) ($view->total_rows ?: 0) !== 1) {
      return;
    }
    $entity = $view->result[0]?->_entity ?? NULL;
    if (!$entity instanceof Unit) {
      return;
    }
    // card_heading_level is not a real entity field.
    // @phpstan-ignore-next-line property.notFound
    $entity->card_heading_level = 'h3';
  }

  /**
   * Implements hook_preprocess_HOOK() for views_view__service_units.
   *
   * Hides the result count when the service_units view has a single result.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  #[Hook('preprocess_views_view__service_units')]
  public function preprocessServiceUnitsView(array &$variables): void {
    /** @var \Drupal\views\ViewExecutable $view */
    $view = $variables['view'];

    if ((int) ($view->total_rows ?: 0) !== 1) {
      return;
    }
    $variables['show_count_container'] = FALSE;
  }

  /**
   * Implements hook_ENTITY_TYPE_build_defaults_alter() for tpr_unit.
   *
   * Ccode may set card_heading_level on the entity to change
   * the rendered heading tag. Append card_heading_level to the cache
   * keys so each variant gets its own cache entry.
   *
   * @phpstan-param array<string, mixed> $build
   *
   * @see self::viewsPostExecute
   */
  #[Hook('tpr_unit_build_defaults_alter')]
  public function buildDefaultsAlter(array &$build, EntityInterface $entity, string $view_mode): void {
    if (!empty($entity->card_heading_level)) {
      $build['#cache']['keys'][] = 'card_heading_level:' . $entity->card_heading_level;
    }
  }

}
