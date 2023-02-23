<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Commands;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Update\UpdateHookRegistry;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;

/**
 * Drush command to help with 2.x to 3.x update.
 */
final class MajorUpdateCommands extends DrushCommands {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Update\UpdateHookRegistry $updateHookRegistry
   *   The update hook registry.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   */
  public function __construct(
    private UpdateHookRegistry $updateHookRegistry,
    private Connection $database,
    private ModuleHandlerInterface $moduleHandler,
    private ModuleExtensionList $moduleExtensionList,
  ) {
  }

  /**
   * The module map.
   *
   * @return array
   *   The module map.
   */
  private function getModuleMap() : array {
    return [
      'api_tools' => NULL,
      'aet' => NULL,
      'config_update' => NULL,
      'hdbt_admin_editorial' => 'hdbt_admin_tools',
      'hdbt_component_library' => 'hdbt_admin_tools',
      'hdbt_content' => 'hdbt_admin_tools',
      'helfi_announcements' => 'helfi_node_announcement',
      'helfi_base_config' => 'helfi_base_content',
      'helfi_charts' => 'helfi_paragraphs_chart',
      'helfi_contact_cards' => 'helfi_paragraphs_contact_card_listing',
      'helfi_content' => 'helfi_base_content',
      'helfi_events' => 'helfi_react_search',
      'helfi_gdpr_compliance' => 'helfi_eu_cookie_compliance',
      'helfi_hotjar' => NULL,
      'helfi_languages' => NULL,
      'helfi_matomo_config' => NULL,
      'helfi_media_formtool_config' => NULL,
      'helfi_media_map_config' => 'helfi_media_map',
      'helfi_news_feed' => 'helfi_paragraphs_news_list',
      'helfi_news_item' => 'helfi_node_news_item',
      'helfi_profile_block' => NULL,
      'helfi_siteimprove_config' => NULL,
      'helfi_tpr_unit_districts' => NULL,
      'media_entity_soundcloud' => NULL,
      'token_filter' => NULL,
      'update_helper' => NULL,
      // @todo check these.
      'helfi_helsinki_neighbourhoods' => NULL,
      'helfi_announcements_tpr' => NULL,
      'select2_icon' => 'hdbt_admin_tools',
    ];
  }

  /**
   * Replaces the config for given module.
   *
   * @param string $configExportFolder
   *   The configuration export folder.
   * @param string $module
   *   The nodule config to replace.
   */
  private function replaceConfig(string $configExportFolder, string $module) : void {
    $configFolder = sprintf('%s/config', $this->moduleExtensionList->getPath($module));

    $ignoredModules = [
      'helfi_user_roles',
    ];

    // Never replace config from ignored modules.
    if (in_array($module, $ignoredModules)) {
      return;
    }

    if (!is_dir($configFolder)) {
      return;
    }
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($configFolder)
    );
    foreach ($iterator as $item) {
      if (!$item->isFile()) {
        continue;
      }
      $fileName = str_replace($configFolder, '', $item->getPathname());

      if (str_contains($fileName, 'schema')) {
        continue;
      }
      $fileName = str_replace(['rewrite', 'optional', 'install'], '', $fileName);
      $fileName = ltrim($fileName, '/');

      $fileContent = file_get_contents($item->getPathName());
      file_put_contents($configExportFolder . '/' . $fileName, $fileContent);
    }
  }

  /**
   * Disables modules without running uninstall tasks.
   *
   * @param array $modules
   *   The module list.
   */
  private function forceDisableModules(array $modules) : void {
    foreach ($modules as $oldModule => $newModule) {
      $this->database->delete('key_value')
        ->condition('name', $oldModule)
        ->execute();
    }
  }

  /**
   * Enables modules without running installation tasks.
   *
   * @param array $modules
   *   The module list.
   */
  private function forceEnableModules(array $modules) : void {
    foreach ($modules as $module) {
      if ($this->updateHookRegistry->getInstalledVersion($module) > UpdateHookRegistry::SCHEMA_UNINSTALLED) {
        continue;
      }
      $this->database->insert('key_value')
        ->fields([
          'collection' => 'system.schema',
          'name' => $module,
          'value' => 9000,
        ])
        ->execute();
    }
  }

  /**
   * Force runs install hooks for given modules.
   *
   * @param array $modules
   *   An array of module hooks to run.
   */
  private function runInstallHooks(array $modules) : void {
    foreach ($modules as $module) {
      $this->moduleHandler->loadInclude($module, 'install');

      if (!function_exists($module . '_install')) {
        continue;
      }
      call_user_func($module . '_install', FALSE);
    }
  }

  /**
   * Gets the base modules.
   *
   * @return array
   *   An array of base modules.
   */
  private function getBaseModules() : array {
    static $modules = [];

    if ($modules) {
      return $modules;
    }
    $path = $this->moduleExtensionList->getPath('helfi_platform_config_base');
    $fileContent = Yaml::decode(file_get_contents($path . '/helfi_platform_config_base.info.yml'));

    $modules = array_map(function (string $line) : string {
      return explode(':', $line)[0];
    }, $fileContent['dependencies']);

    // Enable TPR related modules if TPR is enabled.
    if ($this->moduleHandler->moduleExists('helfi_tpr')) {
      $modules[] = 'helfi_tpr_config';
      $modules[] = 'helfi_paragraphs_content_liftup';
    }

    // Make sure 'helfi_paragraphs_news_list' module is enabled if it was
    // previously enabled.
    if (
      $this->moduleHandler->moduleExists('helfi_news_feed') ||
      in_array('helfi_news_feed', $this->getExtensions()['modules'])
    ) {
      $modules[] = 'helfi_paragraphs_news_list';
    }

    // Enable helfi_platform_config_base module.
    $modules[] = 'helfi_platform_config_base';

    return $modules;
  }

  /**
   * Gets the contents of core.extension.yml.
   *
   * @return array
   *   The contents of core extension yaml as an array.
   */
  private function getExtensions(): array {
    $configExportFolder = \Drupal::root() . '/../conf/cmi';
    return Yaml::decode(
      file_get_contents($configExportFolder . '/core.extension.yml')
    );
  }

  /**
   * Runs config update.
   */
  #[Command(name: 'helfi:platform-config:update-config')]
  public function updateConfig() : void {
    $modules = $this->getBaseModules();
    $moduleMap = $this->getModuleMap();
    $this->forceDisableModules($moduleMap);
    $this->forceEnableModules($modules);

    $configExportFolder = \Drupal::root() . '/../conf/cmi';
    $obsoleteFiles = [
      'select2_icon.settings.yml',
      'helfi_news_feed.settings.yml',
    ];

    foreach ($obsoleteFiles as $file) {
      if (file_exists("$configExportFolder/$file")) {
        unlink("$configExportFolder/$file");
      }
    }
    $extensions = $this->getExtensions();
    $extensionsConfig = \Drupal::configFactory()->getEditable('core.extension');

    foreach ($extensions['module'] as $module => $weight) {
      if (!in_array($module, array_keys($moduleMap))) {
        continue;
      }
      unset($extensions['module'][$module]);
    }
    $extensionsConfig->set('module', $extensions['module'])->save();

    foreach ($modules as $module) {
      $extensions['module'][$module] = 0;
    }
    file_put_contents($configExportFolder . '/core.extension.yml', Yaml::encode($extensions));

    // Replace config.
    foreach ($modules as $module) {
      $this->replaceConfig($configExportFolder, $module);
    }
  }

  /**
   * Runs database updates.
   */
  #[Command(name: 'helfi:platform-config:update-database')]
  public function updateDatabase() : void {
    $this->runInstallHooks($this->getBaseModules());
    helfi_platform_config_update_paragraph_target_types();

    // Manually update editoria11y module from 1.x to 2.x.
    if (
      $this->moduleHandler->moduleExists('editoria11y') &&
      $this->updateHookRegistry->getInstalledVersion('editoria11y') < 9001
    ) {
      foreach (['editoria11y_update_9001', 'editoria11y_update_9003'] as $updateHook) {
        $this->moduleHandler->loadInclude('editoria11y', 'install');
        if (!function_exists($updateHook)) {
          continue;
        }
        call_user_func_array($updateHook, [&$sandbox]);
      }
    }
  }

}
