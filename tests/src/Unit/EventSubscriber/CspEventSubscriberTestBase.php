<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\csp\Csp;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Base class for Csp EventSubscriber tests.
 */
abstract class CspEventSubscriberTestBase extends UnitTestCase {

  use ProphecyTrait;
  use EnvironmentResolverTrait;

  /**
   * The EnvironmentResolverInterface.
   */
  protected EnvironmentResolverInterface $environmentResolver;

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
   * The config factory.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $configFactory;

  /**
   * The ModuleHandlerInterface.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $moduleHandler;

  /**
   * The EventSubscriber to test.
   *
   * @var \Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase
   */
  protected CspSubscriberBase $eventSubscriber;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->event = $this->prophesize(PolicyAlterEvent::class);
    $this->policy = $this->prophesize(Csp::class);
    $this->event->getPolicy()->willReturn($this->policy->reveal());

    $this->environmentResolver = $this->getEnvironmentResolver(Project::ASUMINEN, EnvironmentEnum::Local->value);
    $this->configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
  }

  /**
   * Tests the getSubscribedEvents method.
   *
   * @covers ::getSubscribedEvents
   */
  public function testGetSubscribedEvents(): void {
    $this->assertEquals([CspEvents::POLICY_ALTER => 'policyAlter'], $this->eventSubscriber->getSubscribedEvents());
  }

}
