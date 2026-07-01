<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\SchemaOrg;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_platform_config\SchemaOrg\EntityIdTrait;
use Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Emits a public-sector Place entity for tpr_unit (service location) pages.
 */
final class PlaceBuilder implements SchemaBuilderInterface {

  use EntityIdTrait;
  use PlainTextTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(?EntityInterface $entity): bool {
    return $entity instanceof Unit;
  }

  /**
   * {@inheritdoc}
   */
  public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    assert($entity instanceof Unit);

    // Output depends on this entity and varies by content language.
    $cacheability
      ->addCacheableDependency($entity)
      ->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);

    $place = [
      // @todo We should parse service type from Unit and use better subtype
      // of Place, e.g. MedicalClinic, School, ChildCare, Preschool, Library,
      // etc. and fall back to Place only if the detection fails.
      '@type' => 'Place',
      '@id' => $this->buildId($entity, 'place'),
      'mainEntityOfPage' => ['@id' => $this->buildId($entity, 'webpage')],
      'name' => (string) $entity->label(),
      'description' => $this->cleanText($entity->getDescription('summary')),
      'url' => $this->buildWebsiteUrl($entity),
      'telephone' => $entity->get('phone')->value,
      'email' => $entity->get('email')->value,
      'address' => $this->buildAddress($entity),
      'geo' => $this->buildGeo($entity),
    ];

    return [$place];
  }

  /**
   * Reads the unit website link as an absolute URL string.
   *
   * @param \Drupal\helfi_tpr\Entity\Unit $unit
   *   The unit entity.
   *
   * @return string|null
   *   The website URL, or NULL when the link field is empty or invalid.
   */
  private function buildWebsiteUrl(Unit $unit): ?string {
    $link = $unit->get('www')->first();
    if (!$link instanceof LinkItem) {
      return NULL;
    }
    try {
      return $link->getUrl()->setAbsolute()->toString();
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Builds a PostalAddress node from the unit address field.
   *
   * @param \Drupal\helfi_tpr\Entity\Unit $unit
   *   The unit entity.
   *
   * @return array<string, mixed>
   *   The PostalAddress node; empty parts are dropped by the manager.
   */
  private function buildAddress(Unit $unit): array {
    $address = $unit->get('address')->first();
    if (!$address) {
      return [];
    }
    $values = $address->getValue();
    return [
      '@type' => 'PostalAddress',
      'streetAddress' => $values['address_line1'] ?? NULL,
      'postalCode' => $values['postal_code'] ?? NULL,
      'addressLocality' => $values['locality'] ?? NULL,
      'addressCountry' => $values['country_code'] ?? NULL,
    ];
  }

  /**
   * Builds a GeoCoordinates node from the unit latitude/longitude.
   *
   * @param \Drupal\helfi_tpr\Entity\Unit $unit
   *   The unit entity.
   *
   * @return array<string, mixed>
   *   The GeoCoordinates node, or an empty array when either value is missing.
   */
  private function buildGeo(Unit $unit): array {
    $latitude = $unit->get('latitude')->value;
    $longitude = $unit->get('longitude')->value;

    if ($latitude === NULL || $latitude === '' || $longitude === NULL || $longitude === '') {
      return [];
    }

    return [
      '@type' => 'GeoCoordinates',
      'latitude' => (float) $latitude,
      'longitude' => (float) $longitude,
    ];
  }

}
