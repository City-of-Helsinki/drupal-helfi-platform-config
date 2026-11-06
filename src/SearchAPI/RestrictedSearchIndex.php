<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SearchAPI;

use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;

/**
 * Shared search indexes and the main instance for them.
 */
final class RestrictedSearchIndex {

  /**
   * Search API index ids mapped to the instance that may change them.
   *
   * All instances are allowed to do regular index updates on document level,
   * but only the main instance is allowed to do full index clear etc.
   *
   * @var array<string, string>
   */
  private const array MAINS = [
    'embeddings' => Project::ETUSIVU,
    'suggestions' => Project::ETUSIVU,
    'hyte' => Project::ASUMINEN,
  ];

  public function __construct(
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * The instance that may change this index.
   *
   * @return string|null
   *   The project name, or NULL when the index is not shared.
   */
  public function mainInstance(string $indexId): ?string {
    return self::MAINS[$indexId] ?? NULL;
  }

  /**
   * Decides the action for each given index id.
   *
   * The main instance is asked to confirm. Every other instance is blocked.
   * Indexes that are not shared are allowed.
   *
   * @param string[] $indexIds
   *   Index ids to decide for. The result uses this order.
   *
   * @return array<string, \Drupal\helfi_platform_config\SearchAPI\RestrictedClearAction>
   *   Actions keyed by the given index ids.
   */
  public function actionFor(array $indexIds): array {
    $project = $this->activeProject();
    $actions = [];
    foreach ($indexIds as $indexId) {
      $main = $this->mainInstance($indexId);
      if ($main === NULL) {
        $actions[$indexId] = RestrictedClearAction::Allow;
      }
      elseif ($project === $main) {
        $actions[$indexId] = RestrictedClearAction::Confirm;
      }
      else {
        $actions[$indexId] = RestrictedClearAction::Block;
      }
    }
    return $actions;
  }

  /**
   * The active project name, or NULL when it cannot be resolved.
   */
  private function activeProject(): ?string {
    try {
      return $this->environmentResolver->getActiveProject()->getName();
    }
    catch (EnvironmentResolverException) {
      return NULL;
    }
  }

}
