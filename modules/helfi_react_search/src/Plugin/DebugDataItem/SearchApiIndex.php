<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\DebugDataItem;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the debug_data_item.
 */
#[DebugDataItem(
  id: 'search_api',
  title: new TranslatableMarkup('SearchApi index'),
)]
final class SearchApiIndex extends DebugDataItemPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get(EntityTypeManagerInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function collect(): array {
    $data = [];

    if (!$this->entityTypeManager->hasDefinition('search_api_index')) {
      return [];
    }
    $indexes = $this->entityTypeManager
      ->getStorage('search_api_index')
      ->loadMultiple();

    if (!$indexes) {
      return [];
    }
    /** @var \Drupal\search_api\IndexInterface $index */
    foreach ($indexes as $index) {
      $result = $status = NULL;

      try {
        $status = $index->getServerInstance()?->isAvailable();
        $tracker = $index->getTrackerInstance();

        $result = $this->resolveResult(
          $tracker->getIndexedItemsCount(),
          $tracker->getTotalItemsCount()
        );

      }
      catch (SearchApiException) {
      }
      $data[] = [
        'id' => $index->getOriginalId(),
        'result' => $result,
        'status' => $status,
      ];
    }

    return $data;
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

}
