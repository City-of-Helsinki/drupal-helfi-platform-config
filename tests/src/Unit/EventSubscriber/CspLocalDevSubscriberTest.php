<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspLocalDevSubscriber;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Argument;

/**
 * Unit tests for CspLocalDevSubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspLocalDevSubscriber
 */
class CspLocalDevSubscriberTest extends CspEventSubscriberTestBase {

  /**
   * The Environment.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $environment;

  /**
   * The Project.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $project;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->environment = $this->prophesize(Environment::class);
    $this->project = $this->prophesize(Project::class);
    $this->environmentResolver->getActiveEnvironmentName()->willReturn(EnvironmentEnum::Local->value);
    $this->environmentResolver->getActiveProject()->willReturn($this->project->reveal());
    $this->environmentResolver->getEnvironment(Argument::any(), Argument::any())->willReturn($this->environment->reveal());
    $this->environment->getEnvironment()->willReturn(EnvironmentEnum::Local);
    $this->environment->getBaseUrl()->willReturn('https://local.example.com');

    $this->eventSubscriber = new CspLocalDevSubscriber(
      $this->environmentResolver->reveal(),
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
      $this->policyHelper->reveal(),
    );
  }

  /**
   * Tests policy alteration with local environment.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithLocalEnvironment(): void {
    $this->project->getName()->willReturn(Project::ASUMINEN);

    foreach ([
      'script-src',
      'connect-src',
      'style-src',
    ] as $directive) {
      $this->policy->fallbackAwareAppendIfEnabled($directive, 'https://local.example.com')->shouldBeCalled();
    }

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policy alteration doesn't happen on etusivu.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithEtusivu(): void {
    $this->project->getName()->willReturn(Project::ETUSIVU);

    $this->policy->fallbackAwareAppendIfEnabled(Argument::any(), Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policy alteration doesn't happen when not on local environment.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithNotLocalEnvironment(): void {
    $this->project->getName()->willReturn(Project::ETUSIVU);
    $this->environment->getEnvironment()->willReturn(EnvironmentEnum::Test);

    $this->policy->fallbackAwareAppendIfEnabled(Argument::any(), Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
