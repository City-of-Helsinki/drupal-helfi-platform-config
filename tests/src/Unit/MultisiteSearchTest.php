<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\helfi_platform_config\MultisiteSearch;
use Prophecy\PhpUnit\ProphecyTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Prophecy\Prophecy\ObjectProphecy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\IndexInterface;

/**
 * Tests the MultisiteSearch service.
 */
class MultisiteSearchTest extends UnitTestCase {

  use ProphecyTrait;
  use EnvironmentResolverTrait;

  /**
   * The service to test.
   */
  protected MultisiteSearch $multisiteSearch;

  /**
   * The mocked entity type manager.
   */
  protected ObjectProphecy $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityTypeManager->getStorage('search_api_index')->willReturn($this->prophesize(IndexInterface::class)->reveal());

    $this->multisiteSearch = new MultisiteSearch($this->getEnvironmentResolver(Project::ETUSIVU, EnvironmentEnum::Local), $this->entityTypeManager->reveal());
  }

  /**
   * Tests the getInstanceIndexPrefix method.
   */
  public function testGetInstanceIndexPrefix(): void {
    $this->assertEquals('site_etusivu/', $this->multisiteSearch->getInstanceIndexPrefix());
  }

  /**
   * Tests the getInstanceIndexPrefix method with unknown project.
   */
  public function testGetInstanceIndexPrefixWithUnknownProject(): void {
    $multisiteSearch = new MultisiteSearch($this->getEnvironmentResolver(), $this->entityTypeManager->reveal());
    $this->assertNull($multisiteSearch->getInstanceIndexPrefix());
  }

  /**
   * Tests the hasCurrentInstancePrefix method.
   */
  public function testHasCurrentInstancePrefix(): void {
    $this->assertTrue($this->multisiteSearch->hasCurrentInstancePrefix('site_etusivu/123'));
    $this->assertFalse($this->multisiteSearch->hasCurrentInstancePrefix('site_other_project/123'));
  }

  /**
   * Tests the hasAnyInstancePrefix method.
   */
  public function testHasAnyInstancePrefix(): void {
    $this->assertTrue($this->multisiteSearch->hasAnyInstancePrefix('site_etusivu/123'));
    $this->assertTrue($this->multisiteSearch->hasAnyInstancePrefix('site_other_project/123'));
    $this->assertFalse($this->multisiteSearch->hasAnyInstancePrefix('other_project/foo:bar/123:321'));
    $this->assertFalse($this->multisiteSearch->hasAnyInstancePrefix('foo:bar/123:321'));
  }

  /**
   * Tests the addPrefixToId method.
   */
  public function testAddPrefixToId(): void {
    $this->assertEquals('site_etusivu/123', $this->multisiteSearch->addPrefixToId('123'));
    $this->assertEquals('site_etusivu/foo:bar/123:321', $this->multisiteSearch->addPrefixToId('foo:bar/123:321'));
    $this->assertEquals('site_etusivu/123', $this->multisiteSearch->addPrefixToId('site_etusivu/123'));
    $this->assertEquals('site_other_project/123', $this->multisiteSearch->addPrefixToId('site_other_project/123'));
  }

}
