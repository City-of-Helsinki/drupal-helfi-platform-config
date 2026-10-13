<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ProjectMetadata;
use Drupal\helfi_platform_config\RestrictedClearAction;
use Drupal\helfi_platform_config\RestrictedSearchIndex;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for RestrictedSearchIndex.
 */
#[CoversClass(RestrictedSearchIndex::class)]
#[Group('helfi_platform_config')]
final class RestrictedSearchIndexTest extends UnitTestCase {

  /**
   * Restricted ids are embeddings and suggestions, in that order.
   */
  public function testMatchingIds(): void {
    $sut = new RestrictedSearchIndex($this->resolver(Project::LIIKENNE));

    $this->assertSame(
      ['embeddings', 'suggestions'],
      $sut->matchingIds(['suggestions', 'hyte', 'news', 'embeddings', 'contacts']),
    );
    $this->assertSame([], $sut->matchingIds(['hyte', 'news', 'contacts', 'search_promotions']));
  }

  /**
   * Only the etusivu project is treated as the etusivu instance.
   */
  public function testIsEtusivuInstance(): void {
    $etusivu = new RestrictedSearchIndex($this->resolver(Project::ETUSIVU));
    $other = new RestrictedSearchIndex($this->resolver(Project::LIIKENNE));

    $this->assertTrue($etusivu->isEtusivuInstance());
    $this->assertFalse($other->isEtusivuInstance());
  }

  /**
   * An unresolved project is not etusivu, so a shared clear stays blocked.
   */
  public function testUnresolvedProjectIsNotEtusivu(): void {
    $resolver = $this->createMock(EnvironmentResolverInterface::class);
    $resolver->method('getActiveProject')
      ->willThrowException(new EnvironmentResolverException('Project is not configured.'));

    $sut = new RestrictedSearchIndex($resolver);

    $this->assertFalse($sut->isEtusivuInstance());
    $this->assertSame(RestrictedClearAction::Block, $sut->actionFor(['embeddings']));
  }

  /**
   * Clears are allowed, blocked, or confirmed from the instance and the ids.
   */
  public function testActionFor(): void {
    $etusivu = new RestrictedSearchIndex($this->resolver(Project::ETUSIVU));
    $other = new RestrictedSearchIndex($this->resolver(Project::LIIKENNE));

    $this->assertSame(RestrictedClearAction::Allow, $other->actionFor(['hyte', 'news']));
    $this->assertSame(RestrictedClearAction::Block, $other->actionFor(['news', 'embeddings']));
    $this->assertSame(RestrictedClearAction::Confirm, $etusivu->actionFor(['suggestions']));
    $this->assertSame(RestrictedClearAction::Allow, $etusivu->actionFor(['hyte']));
  }

  /**
   * Schema changes are blocked only for the shared indexes.
   */
  public function testBlocksSchemaChange(): void {
    $etusivu = new RestrictedSearchIndex($this->resolver(Project::ETUSIVU));
    $other = new RestrictedSearchIndex($this->resolver(Project::LIIKENNE));
    $resolver = $this->createMock(EnvironmentResolverInterface::class);
    $resolver->method('getActiveProject')
      ->willThrowException(new EnvironmentResolverException('Project is not configured.'));
    $unresolved = new RestrictedSearchIndex($resolver);

    $this->assertTrue($other->blocksSchemaChange('embeddings'));
    $this->assertTrue($other->blocksSchemaChange('suggestions'));
    $this->assertFalse($other->blocksSchemaChange('hyte'));
    $this->assertFalse($other->blocksSchemaChange('news'));
    $this->assertFalse($etusivu->blocksSchemaChange('embeddings'));
    $this->assertFalse($etusivu->blocksSchemaChange('suggestions'));
    $this->assertTrue($unresolved->blocksSchemaChange('embeddings'));
  }

  /**
   * Builds a resolver whose active project has the given name.
   */
  private function resolver(string $projectName): EnvironmentResolverInterface {
    $resolver = $this->createMock(EnvironmentResolverInterface::class);
    $resolver->method('getActiveProject')->willReturn(new Project(
      $projectName,
      new ProjectMetadata('https://github.com/city-of-helsinki/drupal-helfi-etusivu'),
    ));
    return $resolver;
  }

}
