<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\Image\LiftupImageManager;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Indexes an absolute liftup image URL.
 */
#[SearchApiProcessor(
  id: 'liftup_image_url',
  label: new TranslatableMarkup('Liftup image'),
  description: new TranslatableMarkup('Indexes an absolute liftup image URL.'),
  stages: [
    'add_properties' => 0,
  ],
)]
final class LiftupImageUrl extends ProcessorPluginBase {

  /**
   * The liftup image manager.
   */
  private LiftupImageManager $liftupImageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->liftupImageManager = $container->get(LiftupImageManager::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $properties['liftup_image_url'] = new ProcessorProperty([
        'label' => $this->t('Liftup image URL'),
        'description' => $this->t('Absolute URL of the liftup image.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()?->getValue();
    if (!$entity instanceof EntityInterface) {
      return;
    }

    $url = $this->liftupImageManager->buildUrl($entity);
    if (!$url) {
      return;
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(FALSE), NULL, 'liftup_image_url');
    foreach ($fields as $field) {
      $field->addValue($url);
    }
  }

}
