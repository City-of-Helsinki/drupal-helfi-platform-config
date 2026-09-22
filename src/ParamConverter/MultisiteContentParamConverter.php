<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\ParamConverter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\helfi_platform_config\MultisiteContentId;
use Symfony\Component\Routing\Route;

/**
 * Upcasts split canonical path segments to a helfi_multisite_content entity.
 */
final class MultisiteContentParamConverter implements ParamConverterInterface {

  /**
   * Constructs the converter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $datasource = $defaults['datasource'] ?? NULL;
    $item = $defaults['item'] ?? NULL;
    if (!is_string($value) || $value === '' || !is_string($datasource) || !is_string($item)) {
      return NULL;
    }

    $id = MultisiteContentId::fromPathSegments($value, $datasource, $item);
    return $this->entityTypeManager
      ->getStorage(MultisiteContentId::ENTITY_TYPE_ID)
      ->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return ($definition['type'] ?? NULL) === 'entity:' . MultisiteContentId::ENTITY_TYPE_ID
      && $name === 'instance'
      && in_array('datasource', $route->compile()->getVariables(), TRUE)
      && in_array('item', $route->compile()->getVariables(), TRUE);
  }

}
