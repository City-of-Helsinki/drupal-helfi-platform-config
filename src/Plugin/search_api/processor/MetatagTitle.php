<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\Token\MetatagTitleResolver;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the entity's customized metatag title to the indexed data.
 *
 * Editors can override the page title via the metatag fields. This processor
 * exposes that overridden title so the search result title can be replaced
 * with it instead of the plain entity label.
 *
 * The processor depends on the metatag module, but that dependency is optional:
 * it is removed from the list of available processors when metatag is not
 * installed.
 *
 * @see \Drupal\helfi_platform_config\EventSubscriber\SearchApiSubscriber::onGatheringProcessors()
 */
#[SearchApiProcessor(
  id: 'helfi_metatag_title',
  label: new TranslatableMarkup('Metatag title'),
  description: new TranslatableMarkup("Adds the entity's customized metatag title to the indexed data."),
  stages: [
    'add_properties' => 0,
  ],
)]
final class MetatagTitle extends ProcessorPluginBase {

  /**
   * The metatag title resolver.
   */
  private MetatagTitleResolver $titleResolver;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->titleResolver = $container->get(MetatagTitleResolver::class);
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $properties['helfi_search_title'] = new ProcessorProperty([
        'label' => $this->t('Metatag title'),
        'description' => $this->t("The entity's customized metatag title."),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param \Drupal\search_api\Item\ItemInterface<mixed> $item
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();

    if (!$entity instanceof EntityInterface) {
      return;
    }

    // Metatag overrides are only available on content entities. Fall back to
    // the entity label when the title has not been customized.
    $title = $entity instanceof ContentEntityInterface
      ? $this->titleResolver->resolve($entity)
      : NULL;
    $title ??= (string) $entity->label();

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(FALSE), NULL, 'helfi_search_title');

    foreach ($fields as $field) {
      $field->addValue($title);
    }
  }

}
