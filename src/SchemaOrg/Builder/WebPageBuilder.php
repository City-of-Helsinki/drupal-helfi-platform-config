<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SchemaOrg\Builder;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_platform_config\SchemaOrg\EntityIdTrait;
use Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface;
use Drupal\helfi_platform_config\Token\OGImageManager;

/**
 * Emits a per-page WebPage entity for any content entity page.
 */
final class WebPageBuilder implements SchemaBuilderInterface {

  use EntityIdTrait;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(?EntityInterface $entity): bool {
    return $entity instanceof ContentEntityInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    assert($entity instanceof ContentEntityInterface);

    $config = $this->configFactory->get('helfi_platform_config.schema_settings');
    $organizationId = $config->get('organization_id') ?: self::DEFAULT_ORGANIZATION_ID;
    $websiteId = $config->get('website_id') ?: 'https://www.hel.fi/#website';

    // Output depends on this entity and config, and varies by URL and language.
    $cacheability
      ->addCacheableDependency($entity)
      ->addCacheableDependency($config)
      ->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);

    $webpage = [
      '@type' => 'WebPage',
      '@id' => $this->buildId($entity, 'webpage'),
      'url' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'name' => (string) $entity->label(),
      'inLanguage' => $entity->language()->getId(),
      'isPartOf' => ['@id' => $websiteId],
      'publisher' => ['@id' => $organizationId],
    ];

    // Publication / modification dates where the entity supports them.
    if ($entity->hasField('published_at') && $entity->get('published_at')->value) {
      $webpage['datePublished'] = $this->formatDate($entity->get('published_at')->value);
    }
    if ($entity instanceof EntityChangedInterface && $entity->getChangedTime()) {
      $webpage['dateModified'] = $this->formatDate($entity->getChangedTime());
    }

    return [$webpage];
  }

  /**
   * Formats a timestamp as an ISO 8601 string.
   *
   * @param int|string $timestamp
   *   The UNIX timestamp.
   *
   * @return string
   *   ISO 8601 date string.
   */
  private function formatDate(int|string $timestamp): string {
    return $this->dateFormatter->format((int) $timestamp, 'custom', 'c');
  }

}
