<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\Service\GlobalNavigationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for global menu entities.
 */
class MobileMenuController extends ControllerBase implements ContainerInjectionInterface
{

  /**
   * Site default language code.
   *
   * @var string
   */
  private string $defaultLanguageId;

  private GlobalNavigationService $globalNavigationService;

  /**
   * Constructs a MenuController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager.
   */
  public function __construct(
    LanguageManagerInterface $languageManager,
    GlobalNavigationService $globalNavigationService
  )
  {
    $this->languageManager = $languageManager;
    $this->defaultLanguageId = $this->languageManager->getDefaultLanguage()->getId();
    $this->globalNavigationService = $globalNavigationService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('language_manager'),
      $container->get('helfi_navigation.global_navigation_service'),
    );
  }

  public function mobileMenuJson(): JsonResponse {
    $endpoint = sprintf('/global-menus/%s', 'main');
    $menu = $this->globalNavigationService->makeRequest(Project::ETUSIVU, 'GET', $endpoint);
    return new JsonResponse(json_decode($menu, TRUE));
  }

}
