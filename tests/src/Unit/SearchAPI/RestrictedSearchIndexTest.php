<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\SearchAPI;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
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
   * Each shared index names the field that holds the indexing instance.
   */
  public function testSiteField(): void {
    $sut = new RestrictedSearchIndex($this->resolver(Project::LIIKENNE));

    $this->assertSame('instance', $sut->siteField('embeddings'));
    $this->assertSame('parent_instance', $sut->siteField('suggestions'));
    $this->assertSame('instance', $sut->siteField('hyte'));
    $this->assertNull($sut->siteField('news'));
    $this->assertTrue($sut->isShared('hyte'));
    $this->assertFalse($sut->isShared('news'));
  }

  /**
   * Only shared indexes of another main instance count.
   */
  public function testIsSharedFromOtherInstance(): void {
    $etusivu = new RestrictedSearchIndex($this->resolver(Project::ETUSIVU));
    $this->assertFalse($etusivu->isSharedFromOtherInstance('embeddings'));
    $this->assertTrue($etusivu->isSharedFromOtherInstance('hyte'));
    $this->assertFalse($etusivu->isSharedFromOtherInstance('news'));
  }

  /**
   * An unresolved project is never the main instance.
   */
  public function testUnresolvedProject(): void {
    $sut = new RestrictedSearchIndex($this->getEnvironmentResolver());

    $this->assertTrue($sut->isSharedFromOtherInstance('embeddings'));
    $this->assertTrue($sut->isSharedFromOtherInstance('hyte'));
    $this->assertFalse($sut->isSharedFromOtherInstance('news'));
  }

  /**
   * Builds a resolver whose active project has the given name.
   */
  private function resolver(string $projectName): EnvironmentResolver {
    return $this->getEnvironmentResolver($projectName, EnvironmentEnum::Local);
  }

}
