<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Token;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_platform_config\Token\OGImageBuilderInterface;
use Drupal\helfi_tpr_config\Entity\Unit;

/**
 * OG image for tpr entities.
 */
class UnitImageBuilder implements OGImageBuilderInterface {

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $entity instanceof Unit;
  }

  /**
   * {@inheritDoc}
   */
  public function buildUrl(EntityInterface $entity): ?string {
    assert($entity instanceof Unit);

    /** @var \Drupal\image\ImageStyleInterface $image_style */
    $image_style = $this->entityTypeManager
      ->getStorage('image_style')
      ->load('og_image');

    return $entity->getPictureUrlWithImageStyle($image_style);
  }

}
