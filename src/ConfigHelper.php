<?php
namespace Drupal\helfi_platform_config;

use Symfony\Component\Yaml\Yaml;

/**
 * Functions to handle configurations.
 *
 * @package Drupal\helfi_platform_config
 */
class ConfigHelper
{
    /**
     * Install new configuration.
     *
     * @param string $config_location
     *   Absolute path to the configuration to be created.
     *
     * @param string $config_name
     *   Name of the configuration to install.
     */
    public static function installNewConfig(string $config_location, string $config_name): void
    {
        $config_factory = \Drupal::configFactory();
        $filepath = "{$config_location}{$config_name}.yml";
        $data = Yaml::parse(file_get_contents($filepath));
        if (is_array($data)) {
            $config_factory->getEditable($config_name)->setData($data)->save(TRUE);
        }
    }
}
