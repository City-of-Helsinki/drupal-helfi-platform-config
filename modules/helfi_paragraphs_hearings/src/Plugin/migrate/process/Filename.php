<?php

declare(strict_types = 1);

namespace Drupal\helfi_paragraphs_hearings\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Process plugin to get filename from url.
 *
 * @MigrateProcessPlugin(
 *   id = "filename"
 * )
 *
 * @code
 * publish_on:
 *   plugin: filename
 *   source: url
 * @endcode
 */
class Filename extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\migrate\MigrateSkipProcessException
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) : string {
    $name = '';
    if ($value) {
      $name = basename($value);
      //$name = pathinfo($file, PATHINFO_FILENAME);
    }
    return $name;
  }

}
