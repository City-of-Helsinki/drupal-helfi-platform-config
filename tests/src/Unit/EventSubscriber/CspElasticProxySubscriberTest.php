<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\EventSubscriber;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\ServiceEnum;
use Drupal\helfi_platform_config\EventSubscriber\CspElasticProxySubscriber;

/**
 * Unit tests for CspElasticProxySubscriber.
 *
 * @group helfi_platform_config
 * @coversDefaultClass \Drupal\helfi_platform_config\EventSubscriber\CspElasticProxySubscriber
 */
class CspElasticProxySubscriberTest extends CspEventSubscriberTestBase {

  /**
   * The event class to test.
   */
  protected ?string $eventClass = CspElasticProxySubscriber::class;

  /**
   * Tests appending of directive values.
   *
   * @covers ::policyAlter
   */
  public function testAppendDirectiveValues(): void {
    $this->environmentResolver = $this->getEnvironmentResolver(Project::REKRY, EnvironmentEnum::Local);
    $eventSubscriber = new CspElasticProxySubscriber(
      $this->configFactory->reveal(),
      $this->moduleHandler->reveal(),
      $this->environmentResolver,
      $this->policyHelper->reveal(),
    );

    $this->policy->fallbackAwareAppendIfEnabled('connect-src', [
      'https://elastic-proxy-helfi-rekry.docker.so',
      $this->getEtusivuElasticProxyUrl(),
    ])->shouldBeCalled();

    $eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Tests appending of directive values when project has no Elastic proxy.
   *
   * Core sites still get the etusivu elastic proxy URL.
   *
   * @covers ::policyAlter
   */
  public function testAppendDirectiveValuesWithoutProjectProxy(): void {
    $this->policy->fallbackAwareAppendIfEnabled('connect-src', [
      $this->getEtusivuElasticProxyUrl(),
    ])->shouldBeCalled();

    $this->eventSubscriber->policyAlter($this->event->reveal());
  }

  /**
   * Gets the etusivu elastic proxy URL from the environment resolver.
   */
  private function getEtusivuElasticProxyUrl(): string {
    return $this->environmentResolver
      ->getEnvironment(Project::ETUSIVU, $this->environmentResolver->getActiveEnvironmentName())
      ->getService(ServiceEnum::PublicElasticProxy)
      ->address
      ->getAddress();
  }

}
