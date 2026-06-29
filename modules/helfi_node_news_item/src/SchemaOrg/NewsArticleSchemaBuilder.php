<?php

declare(strict_types=1);

namespace Drupal\helfi_node_news_item\SchemaOrg;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_platform_config\SchemaOrg\DateFormatTrait;
use Drupal\helfi_platform_config\SchemaOrg\EntityIdTrait;
use Drupal\helfi_platform_config\SchemaOrg\SchemaBuilderInterface;

/**
 * Emits a schema.org NewsArticle for the news content types.
 */
class NewsArticleSchemaBuilder implements SchemaBuilderInterface {

  use DateFormatTrait;
  use EntityIdTrait;

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(?EntityInterface $entity): bool {
    return $entity instanceof ContentEntityInterface
      && $entity->getEntityTypeId() === 'node'
      && str_starts_with($entity->bundle(), 'news_');
  }

  /**
   * {@inheritdoc}
   */
  public function build(?EntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    assert($entity instanceof ContentEntityInterface);

    $config = $this->configFactory->get('helfi_platform_config.schema_settings');
    $organizationId = $config->get('organization_id') ?: self::DEFAULT_ORGANIZATION_ID;
    $websiteId = $config->get('website_id') ?: 'https://www.hel.fi/#website';

    // Output depends on this entity and config, and varies by language.
    $cacheability
      ->addCacheableDependency($entity)
      ->addCacheableDependency($config)
      ->addCacheContexts(['languages:' . LanguageInterface::TYPE_CONTENT]);

    $schema = [
      '@type' => 'NewsArticle',
      '@id' => $this->buildId($entity, 'newsarticle'),
      // Tie the article to the WebPage emitted by WebPageBuilder.
      'mainEntityOfPage' => ['@id' => $this->buildId($entity, 'webpage')],
      'url' => $entity->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'headline' => (string) $entity->label(),
      'alternativeHeadline' => $this->fieldValue($entity, 'field_short_title'),
      'description' => $this->fieldValue($entity, 'field_lead_in'),
      'inLanguage' => $entity->language()->getId(),
      'isPartOf' => ['@id' => $websiteId],
      'isAccessibleForFree' => TRUE,
      'publisher' => ['@id' => $organizationId],
      'keywords' => $this->termLabels($entity, 'field_news_item_tags', $cacheability),
      // Only applies to etusivu news:
      'articleSection' => $this->termLabels($entity, 'field_news_groups', $cacheability),
      'contentLocation' => array_map(
        static fn (string $name): array => ['@type' => 'Place', 'name' => $name],
        $this->termLabels($entity, 'field_news_neighbourhoods', $cacheability),
      ),
    ];

    if ($entity->hasField('published_at') && $entity->get('published_at')->value) {
      $schema['datePublished'] = $this->formatDate($entity->get('published_at')->value);
    }
    if ($entity instanceof EntityChangedInterface && $entity->getChangedTime()) {
      $schema['dateModified'] = $this->formatDate($entity->getChangedTime());
    }

    return [$this->extendSchema($schema, $entity, $cacheability)];
  }

  /**
   * Allows subclasses to add bundle-specific properties to the schema.
   *
   * @param array<string, mixed> $schema
   *   The NewsArticle schema assembled from the shared fields.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The news node.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Cacheability for the page.
   *
   * @return array<string, mixed>
   *   The (possibly modified) schema.
   */
  protected function extendSchema(array $schema, ContentEntityInterface $entity, RefinableCacheableDependencyInterface $cacheability): array {
    return $schema;
  }

  /**
   * Returns the scalar value of a field, or NULL when empty/missing.
   */
  protected function fieldValue(ContentEntityInterface $entity, string $fieldName): ?string {
    if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
      return NULL;
    }
    return (string) $entity->get($fieldName)->value;
  }

  /**
   * Collects referenced taxonomy term labels and registers their cache tags.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $fieldName
   *   The entity reference field machine name.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cacheability
   *   Cacheability refined with each referenced term.
   *
   * @return string[]
   *   The list of term labels.
   */
  protected function termLabels(ContentEntityInterface $entity, string $fieldName, RefinableCacheableDependencyInterface $cacheability): array {
    if (!$entity->hasField($fieldName)) {
      return [];
    }

    $field = $entity->get($fieldName);
    assert($field instanceof EntityReferenceFieldItemListInterface);

    $labels = [];
    foreach ($field->referencedEntities() as $term) {
      $cacheability->addCacheableDependency($term);
      $labels[] = (string) $term->label();
    }
    return $labels;
  }

}
