<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspLocalDevSubscriber;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Prophecy\Argument;

/**
 * Unit tests for CspLocalDevSubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspLocalDevSubscriber
 */
class CspLocalDevSubscriberTest extends CspEventSubscriberTestBase {

  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected ?string $eventClass = CspLocalDevSubscriber::class;

  /**
   * Tests policy alteration with local environment.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithLocalEnvironment(): void {
    foreach ([
      'script-src',
      'connect-src',
      'style-src',
    ] as $directive) {
      $this->policy->fallbackAwareAppendIfEnabled($directive, 'https://helfi-etusivu.docker.so')->shouldBeCalled();
    }
    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policy alteration doesn't happen on etusivu.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithEtusivu(): void {
    $this->environmentResolver = $this->getEnvironmentResolver(Project::ETUSIVU, EnvironmentEnum::Local);
    $this->eventSubscriber = new CspLocalDevSubscriber(
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
      $this->environmentResolver,
      $this->policyHelper->reveal(),
    );
    $this->policy->fallbackAwareAppendIfEnabled(Argument::any(), Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policy alteration doesn't happen when not on local environment.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithNotLocalEnvironment(): void {
    $this->environmentResolver = $this->getEnvironmentResolver(Project::ETUSIVU, EnvironmentEnum::Test);
    $this->eventSubscriber = new CspLocalDevSubscriber(
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
      $this->environmentResolver,
      $this->policyHelper->reveal(),
    );
    $this->policy->fallbackAwareAppendIfEnabled(Argument::any(), Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
