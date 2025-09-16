<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Unit;

use DG\BypassFinals;
use Drupal\csp\Csp;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_api_base\Environment\Environment;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\EventSubscriber\CspEventSubscriber;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Unit tests for CspEventSubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspEventSubscriber
 */
class CspEventSubscriberTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The CspEventSubscriber.
   *
   * @var \Drupal\helfi_platform_config\EventSubscriber\CspEventSubscriber
   */
  protected CspEventSubscriber $cspEventSubscriber;

  /**
   * The EnvironmentResolverInterface.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $environmentResolver;

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
   * The Event.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $event;

  /**
   * The Csp policy.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $policy;

  /**
   * The Elastic proxy config.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $elasticProxyConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    BypassFinals::enable();

    $this->environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $this->environment = $this->prophesize(Environment::class);
    $this->project = $this->prophesize(Project::class);
    $this->environmentResolver->getActiveEnvironmentName()->willReturn('local');
    $this->environmentResolver->getActiveProject()->willReturn($this->project->reveal());
    $this->environmentResolver->getEnvironment(Argument::any(), Argument::any())->willReturn($this->environment->reveal());
    $this->environment->getEnvironment()->willReturn(EnvironmentEnum::Local);
    $this->environment->getBaseUrl()->willReturn('https://local.example.com');

    $this->elasticProxyConfig = $this->prophesize(ImmutableConfig::class);
    $this->elasticProxyConfig->get('elastic_proxy_url')->willReturn('');
    $config = $this->prophesize(ConfigFactoryInterface::class);
    $config->get('elastic_proxy.settings')->willReturn($this->elasticProxyConfig->reveal());

    $this->event = $this->prophesize(PolicyAlterEvent::class);
    $this->policy = $this->prophesize(Csp::class);
    $this->event->getPolicy()->willReturn($this->policy->reveal());

    $this->cspEventSubscriber = new CspEventSubscriber($this->environmentResolver->reveal(), $config->reveal());
  }

  /**
   * Tests the getSubscribedEvents method.
   *
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEvents(): void {
    $this->assertEquals([CspEvents::POLICY_ALTER => 'policyAlter'], CspEventSubscriber::getSubscribedEvents());
  }

  /**
   * Tests cleaning of bad directive values.
   *
   * @covers ::policyAlter
   * @covers ::cleanDirectiveValues
   */
  public function testShowRecommendationsNoFields(): void {
    $this->project->getName()->willReturn(Project::ETUSIVU);

    foreach ([
      'script-src',
      'style-src',
      'script-src-elem',
      'style-src-elem',
    ] as $directive) {
      $this->policy->hasDirective($directive)->willReturn(TRUE);
      $this->policy->getDirective($directive)->willReturn(['https://example.com', 'dist']);
      $this->policy->setDirective($directive, ['https://example.com'])->shouldBeCalled();
    }

    $this->cspEventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests cleaning of bad directive values on local environment.
   *
   * @covers ::policyAlter
   * @covers ::cleanDirectiveValues
   */
  public function testPolicyAlterWithLocalEnvironment(): void {
    $this->project->getName()->willReturn(Project::ASUMINEN);
    $this->environment->getEnvironment()->willReturn(EnvironmentEnum::Local);

    foreach ([
      'script-src',
      'style-src',
      'script-src-elem',
      'style-src-elem',
    ] as $directive) {
      $this->policy->hasDirective($directive)->willReturn(TRUE);
      $this->policy->getDirective($directive)->willReturn(['https://example.com', 'dist']);
      $this->policy->setDirective($directive, ['https://example.com'])->shouldBeCalled();
    }

    foreach ([
      'script-src-elem',
      'style-src-elem',
      'connect-src',
    ] as $directive) {
      $this->policy->hasDirective($directive)->willReturn(TRUE);
      $this->policy->appendDirective($directive, 'https://local.example.com')->shouldBeCalled();
    }

    $this->cspEventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests policy alteration with elastic proxy config.
   *
   * @covers ::policyAlter
   */
  public function testPolicyAlterWithElasticProxyConfig(): void {
    $this->elasticProxyConfig->get('elastic_proxy_url')->willReturn('https://elastic.example.com');
    $this->project->getName()->willReturn(Project::ETUSIVU);
    $this->policy->hasDirective(Argument::any())->willReturn(FALSE);
    $this->policy->hasDirective('connect-src')->willReturn(TRUE);
    $this->policy->appendDirective('connect-src', 'https://elastic.example.com')->shouldBeCalled();

    $this->cspEventSubscriber->policyAlter($this->event->reveal());
  }

}
