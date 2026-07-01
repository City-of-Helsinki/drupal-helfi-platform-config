<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\SchemaOrg;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_platform_config\SchemaOrg\EntityIdTrait;
use Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface;
use Drupal\helfi_tpr\Entity\Service;
use Drupal\helfi_tpr\Entity\Unit;

/**
 * Emits a GovernmentService entity for tpr_service pages.
 */
final class GovernmentServiceBuilder implements SchemaBuilderInterface {

  use EntityIdTrait;
  use PlainTextTrait;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(?EntityInterface $entity): bool {
    return $entity instanceof Service;
  }

  /**
   * {@inheritdoc}
   */
  public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    assert($entity instanceof Service);

    $config = $this->configFactory->get('helfi_platform_config.schema_settings');
    $organizationId = $config->get('organization_id') ?: self::DEFAULT_ORGANIZATION_ID;

    // Output depends on this entity and config, and varies by content language.
    $cacheability
      ->addCacheableDependency($entity)
      ->addCacheableDependency($config)
      ->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);

    $service = [
      '@type' => 'GovernmentService',
      '@id' => $this->buildId($entity, 'service'),
      'mainEntityOfPage' => ['@id' => $this->buildId($entity, 'webpage')],
      'name' => (string) $entity->label(),
      'description' => $this->cleanText($entity->getDescription('summary') ?: $entity->getDescription('value')),
      'provider' => ['@id' => $organizationId],
      'areaServed' => [
        '@type' => 'City',
        'name' => 'Helsinki',
      ],
      'availableChannel' => $this->buildServiceLocations($entity, $cacheability),
    ];

    return [$service];
  }

  /**
   * Builds a ServiceChannel item for the TPR unit.
   *
   * @param \Drupal\helfi_tpr\Entity\Service $service
   *   The service entity.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   The shared cacheability, refined with the unit list and each unit.
   *
   * @phpstan-return array<int, array<string, mixed>>
   */
  private function buildServiceLocations(Service $service, RefinableCacheableDependencyInterface $cacheability): array {
    // Respect the editorial toggle that hides service point list.
    if ($service->get('hide_service_points')->value) {
      return [];
    }

    $langcode = $service->language()->getId();
    $storage = $this->entityTypeManager->getStorage('tpr_unit');

    // Published units in the current content language
    // that reference this service.
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('services', $service->id())
      ->condition('content_translation_status', 1)
      ->condition('langcode', $langcode)
      ->execute();

    $cacheability->addCacheTags($storage->getEntityType()->getListCacheTags());

    $channels = [];
    foreach ($storage->loadMultiple($ids) as $unit) {
      assert($unit instanceof Unit);
      $unit = $unit->hasTranslation($langcode) ? $unit->getTranslation($langcode) : $unit;
      $cacheability->addCacheableDependency($unit);

      $channels[] = [
        '@type' => 'ServiceChannel',
        'serviceLocation' => [
          '@id' => $this->buildId($unit, 'place'),
          '@type' => 'Place',
          'name' => (string) $unit->label(),
        ],
      ];
    }

    return $channels;
  }

}
