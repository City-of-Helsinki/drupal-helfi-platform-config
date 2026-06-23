<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Traits;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Site\Settings;

/**
 * Provides a trait for stubbing a config factory for text pipeline tests.
 */
trait IgnoredClassesConfigFactoryTrait {

  /**
   * Builds a config factory returning an ignored_classes list.
   *
   * @phpstan-param string[] $classes
   */
  private function stubIgnoredClassesConfigFactory(array $classes): ConfigFactoryInterface {
    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('ignored_classes')->willReturn($classes);

    $configFactory = $this->prophesize(ConfigFactoryInterface::class);
    $configFactory->get('helfi_search.settings')->willReturn($config->reveal());

    return $configFactory->reveal();
  }

  /**
   * Builds a Settings instance with additional ignored classes.
   *
   * @phpstan-param string[] $additionalClasses
   */
  private function stubIgnoredClassesSettings(array $additionalClasses = []): Settings {
    return new Settings([
      'helfi_search_additional_ignored_classes' => $additionalClasses,
    ]);
  }

}
