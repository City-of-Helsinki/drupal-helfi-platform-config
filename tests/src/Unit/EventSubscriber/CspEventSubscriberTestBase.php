<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use DG\BypassFinals;
use Drupal\csp\Csp;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\csp\PolicyHelper;
use Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
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

  /**
   * The EnvironmentResolverInterface.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $environmentResolver;

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
   * The PolicyHelper.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected ObjectProphecy $policyHelper;

  /**
   * The EventSubscriber to test.
   *
   * @var \Drupal\helfi_platform_config\EventSubscriber\CspSubscriberBase
   */
  protected CspSubscriberBase $eventSubscriber;

  /**
   * The event class to test.
   */
  protected string $eventClass;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    BypassFinals::enable();

    $this->event = $this->prophesize(PolicyAlterEvent::class);
    $this->policy = $this->prophesize(Csp::class);
    $this->event->getPolicy()->willReturn($this->policy->reveal());

    $this->environmentResolver = $this->prophesize(EnvironmentResolverInterface::class);
    $this->configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
    $this->policyHelper = $this->prophesize(PolicyHelper::class);

    if ($this->eventClass) {
      $this->eventSubscriber = new $this->eventClass(
        $this->environmentResolver->reveal(),
        $this->configFactory->reveal(),
        $this->moduleHandler->reveal(),
        $this->policyHelper->reveal(),
      );
    }
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
