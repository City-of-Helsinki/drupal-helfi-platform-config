<?php

namespace Drupal\helfi_platform_config\Plugin\Linkit\Substitution;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\linkit\SubstitutionInterface;
use Drupal\helfi_platform_config\Entity\ExternalEntity\MultisiteNode;

/**
 * A substitution plugin for the absolute URL of an external entity.
 *
 * @Substitution(
 *   id = "multisite",
 *   label = @Translation("Multisite absolute URL"),
 * )
 */
class Multisite extends PluginBase implements SubstitutionInterface {

  /**
   * {@inheritdoc}
   */
  public function getUrl(EntityInterface $entity) {
    if (!$entity instanceof MultisiteNode) {
      return NULL;
    }

    return $entity->getExternalUrl();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(EntityTypeInterface $entity_type) {
    return $entity_type->id() === 'helfi_multisite_node';
  }

}
