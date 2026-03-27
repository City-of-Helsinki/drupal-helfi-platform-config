<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\SearchAPI\Processor\MainImageUrlProcessorBase;
use Drupal\helfi_platform_config\SearchAPI\Processor\MainImageProcessorProperties;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;

/**
 * Indexes District image uri in correct image style.
 */
#[SearchApiProcessor(
  id: 'district_image_absolute_url',
  label: new TranslatableMarkup('Image absolute URL'),
  description: new TranslatableMarkup('Generate absolute URL for image'),
  stages: [
    'add_properties' => 0,
  ],
)]
final class DistrictImageAbsoluteUrl extends MainImageUrlProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected function getFieldProperties(): MainImageProcessorProperties {
    return new MainImageProcessorProperties(
      imageStyleField: 'district_image_absolute_url',
      entityField: 'field_district_image',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function isValid(NodeInterface $node): bool {
    return $node->getType() === 'district';
  }

}
