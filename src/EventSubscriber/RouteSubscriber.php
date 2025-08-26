<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\Announcements;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\Surveys;
use Drupal\helfi_node_announcement\Entity\Announcement;
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
    private AccountInterface $currentUser,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      KernelEvents::REQUEST => [['rerouteParagraphCanonicalUrl'], ['rerouteExternalEntityCanonicalUrl']],
    ];
    return $events;
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

  public function rerouteExternalEntityCanonicalUrl(RequestEvent $event): void {
    $routeName = $this->currentRouteMatch->getRouteName();
    $entityBundles = ['announcement', 'survey'];

    if (str_contains($routeName, 'canonical')) {
      $entity = $this->currentRouteMatch->getParameter('node');
      if (
        $entity &&
        in_array($entity->bundle(), $entityBundles)
      ) {
        $event->setResponse($this->getRedirectByAuthentication());
      }
    }
  }

  /**
   * Prevent users from getting to certain node canonical routes.
   *
   * @return TrustedRedirectResponse
   *   The redirect response based on authentication.
   */
  private function getRedirectByAuthentication(): TrustedRedirectResponse {
    if ($this->currentUser->isAuthenticated()) {
      $content_view = Url::fromRoute('view.content.page_1');
      return new TrustedRedirectResponse($content_view->toString(), 302);
    }

    $frontpage = Url::fromRoute('<front>');
    return new TrustedRedirectResponse($frontpage->toString(), 302);
  }

}
