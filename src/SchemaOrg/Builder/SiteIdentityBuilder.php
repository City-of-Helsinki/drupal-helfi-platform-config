<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\SchemaOrg\Builder;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_platform_config\SchemaOrg\EntityIdTrait;
use Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Emits the City of Helsinki identity on every page.
 */
final readonly class SiteIdentityBuilder implements SchemaBuilderInterface {

  use EntityIdTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file url generator.
   * @param array<string, string> $defaultImages
   *   Default image paths keyed by langcode.
   */
  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private LanguageManagerInterface $languageManager,
    private FileUrlGeneratorInterface $fileUrlGenerator,
    #[Autowire(param: 'helfi_platform_config.default_image')]
    private array $defaultImages,
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
    $logo = $this->defaultImages[$langcode] ?? $this->defaultImages['default'];
    $organization['logo'] = [
      '@type' => 'ImageObject',
      'url' => $this->fileUrlGenerator->generateAbsoluteString($logo),
    ];

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
