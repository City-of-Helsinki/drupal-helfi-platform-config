<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens to the dynamic route events.
 */
final class RouteSubscriber implements EventSubscriberInterface {

  /**
   * EventSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   The current route match.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The account interface.
   */
  public function __construct(
    private CurrentRouteMatch $currentRouteMatch,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => 'rerouteParagraphCanonicalUrl',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function rerouteParagraphCanonicalUrl(RequestEvent $event): void {
    $routeName = $this->currentRouteMatch->getRouteName();
    if ($routeName === 'entity.paragraphs_library_item.canonical') {
      $entity = $this->currentRouteMatch->getParameter('paragraphs_library_item');
      if (!$entity instanceof ContentEntityInterface) {
        return;
      }

      $redirectTo = Url::fromRoute(
        'entity.paragraphs_library_item.entity_usage',
        ['paragraphs_library_item' => $entity->id()]
      );

      $response = new TrustedRedirectResponse($redirectTo->toString(), 302);
      $event->setResponse($response);
    }
  }

}
