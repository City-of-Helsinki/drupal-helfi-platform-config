<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\SearchAPI;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\SearchAPI\RestrictedClearAction;
use Drupal\helfi_platform_config\SearchAPI\RestrictedSearchIndex;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for RestrictedSearchIndex.
 */
#[Group('helfi_platform_config')]
final class RestrictedSearchIndexTest extends UnitTestCase {

  use EnvironmentResolverTrait;

  /**
   * Each shared index names the instance that may change it.
   */
  public function testMainInstance(): void {
    $sut = new RestrictedSearchIndex($this->resolver(Project::LIIKENNE));

    $this->assertSame(Project::ETUSIVU, $sut->mainInstance('embeddings'));
    $this->assertSame(Project::ETUSIVU, $sut->mainInstance('suggestions'));
    $this->assertSame(Project::ASUMINEN, $sut->mainInstance('hyte'));
    $this->assertNull($sut->mainInstance('news'));
  }

  /**
   * Actions follow the incoming ids and each index's main instance.
   */
  public function testActionFor(): void {
    $etusivu = new RestrictedSearchIndex($this->resolver(Project::ETUSIVU));
    $asuminen = new RestrictedSearchIndex($this->resolver(Project::ASUMINEN));
    $other = new RestrictedSearchIndex($this->resolver(Project::LIIKENNE));

    $this->assertSame([
      'news' => RestrictedClearAction::Allow,
      'embeddings' => RestrictedClearAction::Block,
      'hyte' => RestrictedClearAction::Block,
    ], $other->actionFor(['news', 'embeddings', 'hyte']));
    $this->assertSame([
      'suggestions' => RestrictedClearAction::Confirm,
      'hyte' => RestrictedClearAction::Block,
      'news' => RestrictedClearAction::Allow,
    ], $etusivu->actionFor(['suggestions', 'hyte', 'news']));
    $this->assertSame([
      'hyte' => RestrictedClearAction::Confirm,
      'embeddings' => RestrictedClearAction::Block,
    ], $asuminen->actionFor(['hyte', 'embeddings']));
  }

  /**
   * An unresolved project blocks every shared index.
   */
  public function testUnresolvedProjectBlocksSharedIndexes(): void {
    $sut = new RestrictedSearchIndex($this->getEnvironmentResolver());

    $this->assertSame([
      'embeddings' => RestrictedClearAction::Block,
      'hyte' => RestrictedClearAction::Block,
      'news' => RestrictedClearAction::Allow,
    ], $sut->actionFor(['embeddings', 'hyte', 'news']));
  }

  /**
   * Builds a resolver whose active project has the given name.
   */
  private function resolver(string $projectName): EnvironmentResolver {
    return $this->getEnvironmentResolver($projectName, EnvironmentEnum::Local);
  }

}
