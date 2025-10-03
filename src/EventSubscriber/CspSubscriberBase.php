<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\csp\CspEvents;
use Drupal\csp\Event\PolicyAlterEvent;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber base class for CSP policy alteration.
 *
 * @package Drupal\helfi_platform_config\EventSubscriber
 */
abstract class CspSubscriberBase implements EventSubscriberInterface {

  const MODULE_DEPENDENCY = NULL;
  const CONNECT_SRC = [];
  const FONT_SRC = [];
  const FRAME_SRC = [];
  const IMG_SRC = [];
  const MEDIA_SRC = [];
  const SCRIPT_SRC = [];
  const STYLE_SRC = [];

  /**
   * Constructor.
   *
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   The environment resolver.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected readonly EnvironmentResolverInterface $environmentResolver,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];

    if (class_exists(CspEvents::class)) {
      $events[CspEvents::POLICY_ALTER] = 'policyAlter';
    }

    return $events;
  }

  /**
   * Alter CSP policies.
   *
   * @param \Drupal\csp\Event\PolicyAlterEvent $event
   *   The policy alter event.
   */
  public function policyAlter(PolicyAlterEvent $event): void {
    // If module dependency is set, check if the module is enabled.
    if (static::MODULE_DEPENDENCY && !$this->moduleHandler->moduleExists(static::MODULE_DEPENDENCY)) {
      return;
    }

    $policy = $event->getPolicy();

    if (!empty(static::CONNECT_SRC)) {
      $policy->fallbackAwareAppendIfEnabled('connect-src', static::CONNECT_SRC);
    }
    if (!empty(static::FONT_SRC)) {
      $policy->fallbackAwareAppendIfEnabled('font-src', static::FONT_SRC);
    }
    if (!empty(static::FRAME_SRC)) {
      $policy->fallbackAwareAppendIfEnabled('frame-src', static::FRAME_SRC);
    }
    if (!empty(static::IMG_SRC)) {
      $policy->fallbackAwareAppendIfEnabled('img-src', static::IMG_SRC);
    }
    if (!empty(static::MEDIA_SRC)) {
      $policy->fallbackAwareAppendIfEnabled('media-src', static::MEDIA_SRC);
    }
    if (!empty(static::SCRIPT_SRC)) {
      $policy->fallbackAwareAppendIfEnabled('script-src', static::SCRIPT_SRC);
    }
    if (!empty(static::STYLE_SRC)) {
      $policy->fallbackAwareAppendIfEnabled('style-src', static::STYLE_SRC);
    }
  }

}
