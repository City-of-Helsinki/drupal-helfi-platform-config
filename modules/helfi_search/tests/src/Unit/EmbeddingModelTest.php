<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit;

use Drupal\helfi_search\EmbeddingModel;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the EmbeddingModel enum.
 */
#[Group('helfi_search')]
class EmbeddingModelTest extends UnitTestCase {

  /**
   * The default model must always be enabled.
   */
  public function testEnabledAssertion(): void {
    $this->assertContains(EmbeddingModel::DEFAULT, EmbeddingModel::ENABLED);
  }

}
