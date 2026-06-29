<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\SchemaOrg;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_platform_config\SchemaOrg\EntityIdTrait;
use Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface;
use Drupal\helfi_tpr\Entity\Service;

/**
 * Emits a GovernmentService entity for tpr_service pages.
 */
final class GovernmentServiceBuilder implements SchemaBuilderInterface {

  use EntityIdTrait;
  use PlainTextTrait;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
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
      // @todo TPR services should have availableChannel field from service channels.
    ];

    return [$service];
  }

}
