<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_platform_config\EventSubscriber\CspElasticProxySubscriber;
use Prophecy\Argument;
use Drupal\Core\Config\ImmutableConfig;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Unit tests for CspElasticProxySubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspElasticProxySubscriber
 */
class CspElasticProxySubscriberTest extends CspEventSubscriberTestBase {

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

    $this->elasticProxyConfig = $this->prophesize(ImmutableConfig::class);
    $this->elasticProxyConfig->get('elastic_proxy_url')->willReturn('');
    $this->configFactory->get('elastic_proxy.settings')->willReturn($this->elasticProxyConfig->reveal());

    $this->eventSubscriber = new CspElasticProxySubscriber(
      $this->environmentResolver,
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
    $url = 'https://elastic-proxy.example.com';
    $this->elasticProxyConfig->get('elastic_proxy_url')->willReturn($url);

    $this->policy->fallbackAwareAppendIfEnabled('connect-src', [$url])->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests appending of directive values when elastic proxy URL is not set.
   *
   * @covers ::policyAlter
   */
  public function testAppendDirectiveValuesWhenModuleIsNotEnabled(): void {
    $this->policy->fallbackAwareAppendIfEnabled(Argument::any(), Argument::any())->shouldNotBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

}
