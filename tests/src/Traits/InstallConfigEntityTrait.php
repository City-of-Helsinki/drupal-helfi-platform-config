<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Traits;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Config\InstallStorage;

/**
 * Installs single config entities from a module's config/install directory.
 *
 * Use this instead when the module ships config with dependencies that
 * the test does not need.
 *
 * @phpstan-require-extends \Drupal\KernelTests\KernelTestBase
 */
trait InstallConfigEntityTrait {

  /**
   * Installs a config entity from a module's config/install directory.
   *
   * @param string $module
   *   The module that provides the config.
   * @param string $name
   *   The config name.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityInterface
   *   The saved config entity.
   */
  protected function installConfigEntity(string $module, string $name): ConfigEntityInterface {
    $path = $this->container->get(ModuleExtensionList::class)->getPath($module);
    $values = new FileStorage($path . '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY)->read($name);
    $this->assertIsArray($values);

    $entity_type_id = $this->container->get(ConfigManagerInterface::class)->getEntityTypeIdByName($name);
    $this->assertNotNull($entity_type_id);

    $entity = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage($entity_type_id)
      ->create($values);
    $this->assertInstanceOf(ConfigEntityInterface::class, $entity);
    $entity->save();

    return $entity;
  }

}
