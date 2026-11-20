<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_ai\Unit\Ajax;

use Drupal\helfi_ai\Ajax\SummaryStreamCommand;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the AJAX command that starts the summary stream.
 */
#[Group('helfi_ai')]
#[CoversClass(SummaryStreamCommand::class)]
class SummaryStreamCommandTest extends UnitTestCase {

  /**
   * The command carries everything the browser needs to start the stream.
   */
  public function testRender(): void {
    $command = new SummaryStreamCommand('ai-summary-0', 'Page content', 'fi', '/helfi-ai/summary', 'token');

    $this->assertSame([
      'command' => 'helfiAiSummaryStream',
      'wrapperId' => 'ai-summary-0',
      'text' => 'Page content',
      'langcode' => 'fi',
      'url' => '/helfi-ai/summary',
      'csrfToken' => 'token',
    ], $command->render());
  }

}
