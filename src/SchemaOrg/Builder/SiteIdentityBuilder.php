<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SchemaOrg\Builder;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_platform_config\SchemaOrg\EntityIdTrait;
use Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface;
use Drupal\helfi_platform_config\Token\OGImageManager;

/**
 * Emits the City of Helsinki identity on every page.
 */
final readonly class SiteIdentityBuilder implements SchemaBuilderInterface {

  use EntityIdTrait;

  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private LanguageManagerInterface $languageManager,
    private OGImageManager $ogImageManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(?EntityInterface $entity): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    $config = $this->configFactory->get('helfi_platform_config.schema_settings');

    // The identity is driven by config and varies by content language.
    $cacheability
      ->addCacheableDependency($config)
      ->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);

    $organizationId = $config->get('organization_id') ?: self::DEFAULT_ORGANIZATION_ID;
    $organizationName = $config->get('organization_name') ?: 'City of Helsinki';
    $organizationUrl = $config->get('organization_url') ?: 'https://www.hel.fi';

    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    $organization = [
      '@type' => $config->get('organization_type') ?: 'GovernmentOrganization',
      '@id' => $organizationId,
      'name' => $organizationName,
      'url' => $organizationUrl,
    ];

    // Reuse the default OG image as the organization logo.
    if ($logo = $this->ogImageManager->buildUrl(NULL)) {
      $organization['logo'] = [
        '@type' => 'ImageObject',
        'url' => $logo,
      ];
    }

    $website = [
      '@type' => 'WebSite',
      '@id' => $config->get('website_id') ?: 'https://www.hel.fi/#website',
      'name' => $config->get('website_name') ?: $organizationName,
      'url' => $organizationUrl,
      'publisher' => ['@id' => $organizationId],
      'inLanguage' => $langcode,
    ];

    return [$organization, $website];
  }

}
