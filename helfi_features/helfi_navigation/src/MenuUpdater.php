<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Language\LanguageInterface;
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
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    $tree = $this->menuTreeBuilder->buildMenuTree(Menu::MAIN_MENU, $langcode);

    $menu_tree = [
      'name' => $this->siteName($langcode),
      'external' => FALSE,
      'hasItems' => !(empty($tree)),
      'weight' => 0,
      'sub_tree' => $tree,
    ];

    $this->globalNavigationService->makeRequest(
      Project::ETUSIVU,
      'PATCH',
      $this->getGlobalMenuEndpoint(),
      [
        'headers' => [
          'Authorization' => 'Basic ' . $this->config->get('helfi_navigation.api')->get('key'),
        ],
        'json' => [
          'id' => $current_project->getId(),
          'langcode' => $langcode,
          'url' => $this->globalNavigationService->getProjectUrl($current_project->getId(), $langcode),
          'site_name' => $this->siteName($langcode),
          'menu_tree' => $menu_tree,
        ],
      ],
    );
  }

  /**
   * Get translated site name.
   *
   * @return null|string
   *   Returns site name for given language.
   */
  protected function siteName(string $langcode): ? string {
    static $site_names = [];

    if (!$site_names) {
      foreach ($this->languageManager->getLanguages() as $language) {
        $this->languageManager->setConfigOverrideLanguage($language);

        $override = $this->languageManager->getDefaultLanguage()->getId() !== $language->getId();
        $site_name = $this->config
          ->get('system.site')
          ->getOriginal('name', $override);

        $site_names[$language->getId()] = $site_name;
      }
    }
    return $site_names[$langcode] ?? NULL;
  }

  /**
   * Get global menu endpoint.
   *
   * @return string
   *   Global menu endpoint.
   */
  protected function getGlobalMenuEndpoint(): string {
    return sprintf('/api/v1/global-menu/%s', $this->globalNavigationService->getCurrentProject()->getId());
  }

}
