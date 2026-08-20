<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Routing\AdminContext;
use Drupal\helfi_platform_config\HeadingIdInjector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Injects heading anchor ids into the rendered page.
 *
 * @see \Drupal\helfi_platform_config\HeadingIdInjector
 */
final class HeadingIdResponseSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly HeadingIdInjector $injector,
    private readonly AdminContext $adminContext,
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::RESPONSE => ['onResponse', -600],
    ];
  }

  /**
   * Rewrites the heading open tags of an HTML response.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to respond to.
   */
  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();

    if (!$response instanceof HtmlResponse || !$response->isSuccessful()) {
      return;
    }

    if ($this->adminContext->isAdminRoute()) {
      return;
    }

    // Skip AJAX, dialog, htmx etc.
    if ($event->getRequest()->query->has('_wrapper_format')) {
      return;
    }

    $content = $response->getContent();

    if (!is_string($content) || $content === '') {
      return;
    }

    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_URL)
      ->getId();

    $injected = $this->injector->inject($content, $langcode);

    // Replace response HTML.
    if ($injected !== $content) {
      $response->setContent($injected);
    }
  }

}
