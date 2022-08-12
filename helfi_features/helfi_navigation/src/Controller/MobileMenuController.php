<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\Service\ApiManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for global menu entities.
 */
class MobileMenuController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a MobileMenuController object.
   *
   * @param \Drupal\helfi_navigation\Service\ApiManager $globalNavigationService
   *   Global navigation service.
   */
  public function __construct(private ApiManager $globalNavigationService) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('helfi_navigation.global_navigation_service'),
    );
  }

  /**
   * Callback for the mobile menu JSON endpoint.
   */
  public function mobileMenuJson(): JsonResponse {
    $endpoint = sprintf('/global-menus/%s', 'main');
    $menu = $this->globalNavigationService->makeRequest(Project::ETUSIVU, 'GET', $endpoint);
    return new JsonResponse(json_decode($menu, TRUE));
  }

}
