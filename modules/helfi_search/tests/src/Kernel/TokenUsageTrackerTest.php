<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Kernel;

use Drupal\helfi_search\TokenUsageTracker;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the TokenUsageTracker service.
 */
#[Group('helfi_search')]
#[RunTestsInSeparateProcesses]
class TokenUsageTrackerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'helfi_api_base',
    'helfi_search',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('helfi_search', ['helfi_search_token_usage']);
  }

  /**
   * Tests that updateTokenUsage.
   */
  public function testUpdateTokenUsage(): void {
    $sut = $this->container->get(TokenUsageTracker::class);

    $sut->updateTokenUsage('text-embedding-3-small', 100);
    $sut->updateTokenUsage('text-embedding-3-large', 200);
    $sut->updateTokenUsage('text-embedding-3-small', 50);

    $this->assertEquals(150, $sut->getTokenUsage('text-embedding-3-small'));
    $this->assertEquals(200, $sut->getTokenUsage('text-embedding-3-large'));
  }

}
