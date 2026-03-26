<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Plugin\search_api\processor;

use Drupal\helfi_platform_config\SearchAPI\Processor\MainImageUrlProcessorBase;
use Drupal\helfi_platform_config\SearchAPI\Processor\MainImageProcessorProperties;
use Drupal\node\NodeInterface;

/**
 * Get start and end date for daterange field.
 *
 * @SearchApiProcessor(
 *    id = "district_image_absolute_url",
 *    label = @Translation("Image absolute URL"),
 *    description = @Translation("Generate absolute URL for image"),
 *    stages = {
 *      "add_properties" = 0,
 *    },
 *    locked = true,
 *    hidden = true,
 * )
 */
class DistrictImageAbsoluteUrl extends MainImageUrlProcessorBase {

  /**
   * {@inheritdoc}
   */
  protected function getFieldProperties(): MainImageProcessorProperties {
    return new MainImageProcessorProperties(
      searchApiField: 'district_image_absolute_url',
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
