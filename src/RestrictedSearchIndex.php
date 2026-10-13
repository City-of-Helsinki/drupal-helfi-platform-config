<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;

/**
 * Indexes that more than one instance writes to, except hyte.
 *
 * Hyte is also written by several instances, but etusivu isn't one of them,
 * so the logic of using etusivu as the main instance is not applicable here.
 */
final class RestrictedSearchIndex {

  /**
   * Search API index ids that only etusivu may clear.
   */
  public const array IDS = [
    'embeddings',
    'suggestions',
  ];

  public function __construct(
    private readonly EnvironmentResolverInterface $environmentResolver,
  ) {
  }

  /**
   * Whether this Drupal instance is etusivu.
   */
  public function isEtusivuInstance(): bool {
    try {
      return $this->environmentResolver->getActiveProject()->getName() === Project::ETUSIVU;
    }
    catch (EnvironmentResolverException) {
      return FALSE;
    }
  }

  /**
   * Restricted ids among the given index ids, in list order.
   *
   * @param string[] $indexIds
   *   Index ids the clear command would affect.
   *
   * @return string[]
   *   The restricted ids.
   */
  public function matchingIds(array $indexIds): array {
    return array_values(array_intersect(self::IDS, $indexIds));
  }

  /**
   * Decides whether a clear of these indexes is allowed, blocked, or confirmed.
   *
   * @param string[] $indexIds
   *   Index ids the clear command would affect.
   */
  public function actionFor(array $indexIds): RestrictedClearAction {
    if ($this->matchingIds($indexIds) === []) {
      return RestrictedClearAction::Allow;
    }
    return $this->isEtusivuInstance() ? RestrictedClearAction::Confirm : RestrictedClearAction::Block;
  }

  /**
   * Whether changes to this index are blocked on instances other than etusivu.
   *
   * Shared search indexes are left unchanged on instances other than
   * etusivu. Etusivu, and every other index, still create, delete, and remap.
   */
  public function blocksSchemaChange(string $indexId): bool {
    return in_array($indexId, self::IDS, TRUE) && !$this->isEtusivuInstance();
  }

}
