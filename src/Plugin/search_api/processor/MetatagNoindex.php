<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\Helper\MetatagHelper;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Excludes entities with the robots "noindex" directive from being indexed.
 *
 * Editors can hide individual pages from search engines by setting the robots
 * "noindex" metatag. This processor reads that field value and removes such
 * entities from the index.
 *
 * The processor depends on the metatag module, but that dependency is optional:
 * the resolver falls back to a no-op when metatag is not installed.
 *
 * @see \Drupal\helfi_platform_config\Helper\MetatagHelper
 */
#[SearchApiProcessor(
  id: 'helfi_metatag_noindex',
  label: new TranslatableMarkup('Metatag noindex'),
  description: new TranslatableMarkup('Excludes entities that have the robots "noindex" metatag set from being indexed.'),
  stages: [
    'alter_items' => 0,
  ],
)]
final class MetatagNoindex extends ProcessorPluginBase {

  /**
   * The metatag helper.
   */
  private MetatagHelper $metatagHelper;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->metatagHelper = $container->get(MetatagHelper::class);
    return $processor;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string|int, \Drupal\search_api\Item\ItemInterface<mixed>> $items
   */
  public function alterIndexedItems(array &$items): void {
    foreach ($items as $item_id => $item) {
      $entity = $item->getOriginalObject()->getValue();

      if ($entity instanceof ContentEntityInterface && $this->metatagHelper->isNoindex($entity)) {
        unset($items[$item_id]);
      }
    }
  }

}
