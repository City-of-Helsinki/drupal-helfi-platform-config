<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\Menu\Menu;
use Drupal\helfi_navigation\Menu\MenuTreeBuilder;
use Drupal\helfi_navigation\Service\GlobalNavigationService;

/**
 * Synchronizes global menu.
 */
class MenuUpdater {

  /**
   * Constructs MenuUpdater.
   */
  public function __construct(
    protected ConfigFactory $config,
    protected GlobalNavigationService $globalNavigationService,
    protected LanguageManagerInterface $languageManager,
    protected MenuTreeBuilder $menuTreeBuilder,
  ) {}

  /**
   * Sends main menu tree to frontpage instance.
   *
   * @throws \Exception
   *   Throws exception.
   */
  public function syncMenu(): void {
    if ($this->globalNavigationService->inFrontPage()) {
      return;
    }

    $current_project = $this->globalNavigationService->getCurrentProject();
    $menu_tree = [];

    foreach (array_keys($this->languageManager->getLanguages()) as $lang_code) {
      $tree = $this->menuTreeBuilder->buildMenuTree(Menu::MAIN_MENU, $lang_code);

      $menu_tree[$lang_code] = [
        'name' => $this->siteNames()[$lang_code],
        'url' => $this->globalNavigationService->getProjectUrl($current_project->getId(), $lang_code),
        'external' => FALSE,
        'hasItems' => !(empty($tree)),
        'weight' => 0,
        'sub_tree' => $tree,
      ];
    }

    $options = [
      'json' => [
        'id' => $current_project->getId(),
        'url' => $this->globalNavigationService->getProjectUrl($current_project->getId()),
        'site_name' => $this->siteNames(),
        'menu_tree' => $menu_tree,
      ],
    ];

    $this->globalNavigationService->makeRequest(
      Project::ETUSIVU,
      'POST',
      $this->getGlobalMenuEndpoint(),
      $options
    );
  }

  /**
   * Get translated site names.
   *
   * @return array
   *   Returns site names as an array or empty array.
   */
  protected function siteNames(): array {
    $site_names = [];

    foreach ($this->languageManager->getLanguages() as $language) {
      $this->languageManager->setConfigOverrideLanguage($language);
      $override = $this->languageManager->getDefaultLanguage()->getId() !== $language->getId();
      $site_name = $this->config
        ->get('system.site')
        ->getOriginal('name', $override);

      $site_names[$language->getId()] = $site_name;
    }

    return $site_names;
  }

  /**
   * Get global menu endpoint.
   *
   * @return string
   *   Global menu endpoint.
   */
  protected function getGlobalMenuEndpoint(): string {
    return sprintf('/global-menus/%s', $this->globalNavigationService->getCurrentProject()->getId());
  }

}
