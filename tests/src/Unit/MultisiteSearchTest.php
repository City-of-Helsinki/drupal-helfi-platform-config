<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use DG\BypassFinals;
use Drupal\Tests\UnitTestCase;
use Drupal\helfi_platform_config\MultisiteSearch;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\PhpUnit\ProphecyTrait;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;

/**
 * Tests the MultisiteSearch service.
 */
class MultisiteSearchTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The service to test.
   */
  protected MultisiteSearch $multisiteSearch;

  /**
   * The mocked environment resolver project.
   */
  protected ObjectProphecy $environmentResolverProject;

  /**
   * The mocked environment resolver used for this test.
   */
  protected ObjectProphecy $environmentResolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    BypassFinals::enable();
    parent::setUp();

    $this->environmentResolverProject = $this->prophesize(Project::class);
    $this->environmentResolverProject->getName()->willReturn('test_project');
    $this->environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $this->environmentResolver->getActiveProject()->willReturn($this->environmentResolverProject->reveal());
    $this->multisiteSearch = new MultisiteSearch($this->environmentResolver->reveal());
  }

  /**
   * Tests the getInstanceIndexPrefix method.
   */
  public function testGetInstanceIndexPrefix(): void {
    $this->assertEquals('site_test_project/', $this->multisiteSearch->getInstanceIndexPrefix());
  }

  /**
   * Tests the getInstanceIndexPrefix method with unknown project.
   */
  public function testGetInstanceIndexPrefixWithUnknownProject(): void {
    $this->environmentResolver->getActiveProject()->willThrow(new \InvalidArgumentException('No active project found'));
    $this->assertNull($this->multisiteSearch->getInstanceIndexPrefix());
  }

  /**
   * Tests the hasCurrentInstancePrefix method.
   */
  public function testHasCurrentInstancePrefix(): void {
    $this->assertTrue($this->multisiteSearch->hasCurrentInstancePrefix('site_test_project/123'));
    $this->assertFalse($this->multisiteSearch->hasCurrentInstancePrefix('site_other_project/123'));
  }

  /**
   * Tests the hasAnyInstancePrefix method.
   */
  public function testHasAnyInstancePrefix(): void {
    $this->assertTrue($this->multisiteSearch->hasAnyInstancePrefix('site_test_project/123'));
    $this->assertTrue($this->multisiteSearch->hasAnyInstancePrefix('site_other_project/123'));
    $this->assertFalse($this->multisiteSearch->hasAnyInstancePrefix('other_project/foo:bar/123:321'));
    $this->assertFalse($this->multisiteSearch->hasAnyInstancePrefix('foo:bar/123:321'));
  }

  /**
   * Tests the addPrefixToId method.
   */
  public function testAddPrefixToId(): void {
    $this->assertEquals('site_test_project/123', $this->multisiteSearch->addPrefixToId('123'));
    $this->assertEquals('site_test_project/foo:bar/123:321', $this->multisiteSearch->addPrefixToId('foo:bar/123:321'));
    $this->assertEquals('site_test_project/123', $this->multisiteSearch->addPrefixToId('site_test_project/123'));
    $this->assertEquals('site_other_project/123', $this->multisiteSearch->addPrefixToId('site_other_project/123'));
  }

}
