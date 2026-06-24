<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\csp\Csp;
use Drupal\helfi_platform_config\Asset\JsCspHashCollector;
use Drupal\helfi_platform_config\Render\CspHashAttachmentsProcessor;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the CspHashAttachmentsProcessor.
 *
 * @group helfi_platform_config
 */
class CspHashAttachmentsProcessorTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests that CSP hashes are merged when collected during asset rendering.
   */
  public function testProcessAttachmentsMergesHashes(): void {
    $inner = $this->prophesize(AttachmentsResponseProcessorInterface::class);
    $response = new HtmlResponse();
    $hashCollector = new JsCspHashCollector();

    $inner->processAttachments($response)->will(function () use ($hashCollector, $response) {
      $hashCollector->addScriptHash('sha256-example');
      return $response;
    });

    $config = $this->prophesize(Config::class);
    $config->get('external_script_hashes')->willReturn(TRUE);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('helfi_platform_config.csp')->willReturn($config->reveal());

    $processor = new CspHashAttachmentsProcessor(
      $inner->reveal(),
      $hashCollector,
      $configFactory->reveal(),
    );

    $result = $processor->processAttachments($response);
    $attachments = $result->getAttachments();

    $this->assertArrayHasKey('csp_hash', $attachments);
    $this->assertSame([Csp::POLICY_UNSAFE_INLINE], $attachments['csp_hash']['script-src-elem']['sha256-example']);
  }

  /**
   * Tests that processing is skipped when the feature is disabled.
   */
  public function testProcessAttachmentsSkipsWhenDisabled(): void {
    $inner = $this->prophesize(AttachmentsResponseProcessorInterface::class);
    $response = new HtmlResponse();
    $hashCollector = new JsCspHashCollector();
    $hashCollector->addScriptHash('sha256-example');

    $inner->processAttachments($response)->willReturn($response);

    $config = $this->prophesize(Config::class);
    $config->get('external_script_hashes')->willReturn(FALSE);
    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('helfi_platform_config.csp')->willReturn($config->reveal());

    $processor = new CspHashAttachmentsProcessor(
      $inner->reveal(),
      $hashCollector,
      $configFactory->reveal(),
    );

    $result = $processor->processAttachments($response);

    $this->assertSame([], $result->getAttachments());
  }

}
