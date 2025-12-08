<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspLocalDevSubscriber;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\EventSubscriber\CspSentrySubscriber;
use Prophecy\Argument;

/**
 * Unit tests for CspLocalDevSubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspLocalDevSubscriber
 */
class CspLocalDevSubscriberTest extends CspEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->eventSubscriber = new CspSentrySubscriber(
      $this->environmentResolver,
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
    );
  }

  /**
   * Tests policy alteration with local environment.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithLocalEnvironment(): void {
    $environmentResolver = $this->getEnvironmentResolver(Project::ASUMINEN, EnvironmentEnum::Local->value);
    $eventSubscriber = new CspLocalDevSubscriber(
      $environmentResolver,
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
    );

    foreach ([
      'script-src',
      'connect-src',
      'style-src',
    ] as $directive) {
      $this->policy->fallbackAwareAppendIfEnabled($directive, 'https://helfi-etusivu.docker.so')->shouldBeCalled();
    }

    $eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policy alteration doesn't happen on etusivu.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithEtusivu(): void {
    $environmentResolver = $this->getEnvironmentResolver(Project::ETUSIVU, EnvironmentEnum::Local->value);
    $eventSubscriber = new CspLocalDevSubscriber(
      $environmentResolver,
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
    );

    $this->policy->fallbackAwareAppendIfEnabled(Argument::any(), Argument::any())->shouldNotBeCalled();

    $eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policy alteration doesn't happen when not on local environment.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithNotLocalEnvironment(): void {
    $environmentResolver = $this->getEnvironmentResolver(Project::ASUMINEN, EnvironmentEnum::Test->value);
    $eventSubscriber = new CspLocalDevSubscriber(
      $environmentResolver,
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
    );

    $this->policy->fallbackAwareAppendIfEnabled(Argument::any(), Argument::any())->shouldNotBeCalled();

    $eventSubscriber->policyAlter($this->event->reveal());
  }

}
