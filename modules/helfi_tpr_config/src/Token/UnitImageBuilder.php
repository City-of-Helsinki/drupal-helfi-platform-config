<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Token;

use Drupal\Core\Entity\EntityInterface;
use Drupal\helfi_platform_config\Token\OGImageBuilderInterface;
use Drupal\helfi_tpr_config\Entity\Unit;

/**
 * OG image for tpr entities.
 */
class UnitImageBuilder implements OGImageBuilderInterface {

  /**
   * {@inheritDoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $entity instanceof Unit;
  }

  /**
   * {@inheritDoc}
   */
  public function buildUri(EntityInterface $entity): ?string {
    assert($entity instanceof Unit);

    return $entity->getPictureUri();
  }

}
