<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\SearchAPI\Processor\MainImageProcessorProperties;
use Drupal\helfi_platform_config\SearchAPI\Processor\MainImageUrlProcessorBase;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;

/**
 * Indexes main image uri in correct image style.
 */
#[SearchApiProcessor(
  id: 'project_image_absolute_url',
  label: new TranslatableMarkup('Image absolute URL'),
  description: new TranslatableMarkup('Generate absolute URL for image'),
  stages: [
    'add_properties' => 0,
  ],
)]
final class ProjectImageAbsoluteUrl extends MainImageUrlProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected function getFieldProperties(): MainImageProcessorProperties {
    return new MainImageProcessorProperties(
      imageStyleField: 'project_image_absolute_url',
      entityField: 'field_project_image',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function isValid(NodeInterface $node): bool {
    return $node->getType() === 'project';
  }

}
