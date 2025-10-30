<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\DebugDataItem;

use Drupal\Core\DependencyInjection\AutowiredInstanceTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\search_api\Entity\Server;

/**
 * Plugin implementation of the debug_data_item.
 */
#[DebugDataItem(
  id: 'search_api',
  title: new TranslatableMarkup('SearchApi index'),
)]
final class SearchApiIndex extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface, SupportsValidityChecksInterface {

  use AutowiredInstanceTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Resolve return value based on index status.
   *
   * @param int $indexed
   *   Amount of up-to-date items in index.
   * @param int $total
   *   Maximum number of items in index.
   *
   * @return string
   *   Status.
   */
  private function resolveResult(int $indexed, int $total): string {
    if ($indexed == 0 || $total == 0) {
      return 'indexing or index rebuild required';
    }

    if ($indexed === $total) {
      return 'Index up to date';
    }

    return "$indexed/$total";
  }

  /**
   * {@inheritDoc}
   */
  public function check(): bool {
    $servers = $this->entityTypeManager
      ->getStorage('search_api_server')
      ->loadMultiple();

    foreach ($servers as $server) {
      assert($server instanceof Server);

      if (!$server->isAvailable()) {
        return FALSE;
      }
    }

    // All servers are available.
    return TRUE;
  }

}
