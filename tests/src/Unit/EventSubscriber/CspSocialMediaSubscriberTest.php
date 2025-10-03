<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspSocialMediaSubscriber;
use Prophecy\Argument;

/**
 * Unit tests for CspSocialMediaSubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspSocialMediaSubscriber
 */
class CspSocialMediaSubscriberTest extends CspEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->eventSubscriber = new CspSocialMediaSubscriber(
      $this->environmentResolver->reveal(),
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
    );
  }

  /**
   * Tests appending of directive values.
   *
   * @covers ::policyAlter
   */
  public function testAppendDirectiveValues(): void {
    $this->moduleHandler->moduleExists('social_media')->willReturn(TRUE);

    $this->policy->fallbackAwareAppendIfEnabled('connect-src', Argument::any())->shouldBeCalled();
    $this->policy->fallbackAwareAppendIfEnabled('script-src', Argument::any())->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests appending of directive values when module is not enabled.
   *
   * @covers ::policyAlter
   */
  public function testAppendDirectiveValuesWhenModuleIsNotEnabled(): void {
    $this->moduleHandler->moduleExists('social_media')->willReturn(FALSE);

    $this->policy->fallbackAwareAppendIfEnabled(Argument::any(), Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
