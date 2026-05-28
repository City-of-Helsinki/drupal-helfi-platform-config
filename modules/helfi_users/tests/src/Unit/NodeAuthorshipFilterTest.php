<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Unit;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_users\Plugin\views\filter\NodeAuthorshipFilter;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests NodeAuthorshipFilter.
 */
#[CoversClass(NodeAuthorshipFilter::class)]
#[Group('helfi_users')]
class NodeAuthorshipFilterTest extends UnitTestCase {

  /**
   * The mocked current user.
   *
   * @var \Drupal\Core\Session\AccountInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private AccountInterface&MockObject $currentUser;

  /**
   * The mocked views join plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  private PluginManagerInterface&MockObject $joinPluginManager;

  /**
   * The filter plugin under test.
   *
   * @var \Drupal\helfi_users\Plugin\views\filter\NodeAuthorshipFilter
   */
  private NodeAuthorshipFilter $filter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->currentUser = $this->createMock(AccountInterface::class);
    $this->joinPluginManager = $this->createMock(PluginManagerInterface::class);

    $this->filter = new NodeAuthorshipFilter(
      [],
      'helfi_node_authorship',
      [],
      $this->currentUser,
      $this->joinPluginManager,
    );
    $this->filter->options['group'] = '0';
    $this->filter->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests that the filter can be exposed.
   */
  public function testCanExpose(): void {
    $this->assertTrue($this->filter->canExpose());
  }

  /**
   * Tests that adminSummary() returns the correct label for each value.
   *
   * @param string $value
   *   The filter value.
   * @param string $expected
   *   The expected summary string.
   */
  #[DataProvider('adminSummaryProvider')]
  public function testAdminSummary(string $value, string $expected): void {
    $this->filter->value = $value;
    $this->assertSame($expected, $this->filter->adminSummary());
  }

  /**
   * Data provider for testAdminSummary().
   *
   * @return array<string, array{string, string}>
   *   Test cases keyed by description.
   */
  public static function adminSummaryProvider(): array {
    return [
      'either' => ['either', 'Authored or last edited'],
      'authored' => ['authored', 'Authored'],
      'edited' => ['edited', 'Last edited'],
      'unknown returns empty string' => ['unknown', ''],
    ];
  }

  /**
   * Tests query() adds a single WHERE condition for authored-only mode.
   */
  public function testQueryAuthored(): void {
    $this->currentUser->method('id')->willReturn('42');

    $query = $this->createMock(Sql::class);
    $query->expects($this->once())
      ->method('addWhere')
      ->with('0', 'node_field_data.uid', '42');

    $this->filter->query = $query;
    $this->filter->value = 'authored';
    $this->filter->query();
  }

  /**
   * Tests query() adds a revision JOIN and WHERE for edited-only mode.
   */
  public function testQueryEdited(): void {
    $this->currentUser->method('id')->willReturn('42');

    $join = $this->createMock(JoinPluginBase::class);
    $this->joinPluginManager
      ->method('createInstance')
      ->with('standard', $this->isArray())
      ->willReturn($join);

    $query = $this->createMock(Sql::class);
    $query->expects($this->once())
      ->method('addRelationship')
      ->with('node_revision', $join, 'node_field_data');
    $query->expects($this->once())
      ->method('addWhere')
      ->with('0', 'node_revision.revision_uid', '42');

    $this->filter->query = $query;
    $this->filter->value = 'edited';
    $this->filter->query();
  }

  /**
   * Tests query() creates an OR group with both conditions for either mode.
   */
  public function testQueryEither(): void {
    $this->currentUser->method('id')->willReturn('42');

    $join = $this->createMock(JoinPluginBase::class);
    $this->joinPluginManager->method('createInstance')->willReturn($join);

    $query = $this->createMock(Sql::class);
    $query->expects($this->once())
      ->method('addRelationship');
    $query->expects($this->once())
      ->method('setWhereGroup')
      ->with('OR')
      ->willReturn('1');
    $query->expects($this->exactly(2))
      ->method('addWhere');

    $this->filter->query = $query;
    $this->filter->value = 'either';
    $this->filter->query();
  }

  /**
   * Tests query() falls back to either mode for unrecognised values.
   */
  public function testQueryInvalidValueFallsBackToEither(): void {
    $this->currentUser->method('id')->willReturn('42');

    $join = $this->createMock(JoinPluginBase::class);
    $this->joinPluginManager->method('createInstance')->willReturn($join);

    $query = $this->createMock(Sql::class);
    $query->method('setWhereGroup')->willReturn('1');
    $query->expects($this->once())->method('addRelationship');
    $query->expects($this->exactly(2))->method('addWhere');

    $this->filter->query = $query;
    $this->filter->value = 'invalid_value';
    $this->filter->query();
  }

}
