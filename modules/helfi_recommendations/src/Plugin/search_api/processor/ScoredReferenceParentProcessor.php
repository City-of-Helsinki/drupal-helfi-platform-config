<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Indexes scored reference parent data.
 *
 * @SearchApiProcessor(
 *   id = "scored_reference_parent",
 *   label = @Translation("Scored reference parent"),
 *   description = @Translation("Indexes scored reference parent data"),
 *   stages = {
 *     "add_properties" = 0
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
final class ScoredReferenceParentProcessor extends ProcessorPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private RendererInterface $renderer;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DataSourceInterface $datasource = NULL) : array {
    $properties = [];

    if ($datasource) {
      $propertyDefinitions = $datasource->getPropertyDefinitions();
      if (!empty($propertyDefinitions['parent_id']) && !empty($propertyDefinitions['parent_type'])) {
        $fields = [
          'parent_url' => [
            'label' => $this->t('Parent url'),
            'description' => $this->t('Indexes parent entity url'),
          ],
          ...$this->getLanguageSpecificFields('parent_title', $this->t('Parent title')),
          'parent_image_url' => [
            'label' => $this->t('Parent image url'),
            'description' => $this->t('Indexes parent image url'),
          ],
          ...$this->getLanguageSpecificFields('parent_image_alt', $this->t('Parent image alt')),
          'parent_published_at' => [
            'label' => $this->t('Parent published date'),
            'description' => $this->t('Indexes parent published date'),
            'type' => 'date',
          ],
        ];

        foreach ($fields as $field => $definition) {
          $properties[$field] = new ProcessorProperty($definition + [
            'type' => 'string',
            'processor_id' => $this->getPluginId(),
          ]);
        }
      }
    }

    return $properties;
  }

  /**
   * Generates language-specific field definitions.
   *
   * @param string $field_id
   *   The id of the field (e.g., 'parent_title', 'parent_image_alt').
   * @param string|TranslatableMarkup $field_name
   *   The name of the field.
   *
   * @return array
   *   An array of field definitions.
   */
  private function getLanguageSpecificFields(string $field_id, string|TranslatableMarkup $field_name): array {
    $fields = [];
    $languages = ['fi' => 'Finnish', 'sv' => 'Swedish', 'en' => 'English'];

    foreach ($languages as $code => $langname) {
      $fields["{$field_id}_{$code}"] = [
        'label' => $this->t("@field_name in @langname", [
          '@field_name' => $field_name,
          '@langname' => $langname,
        ]),
        'description' => $this->t("Indexes @field_name in @langname", [
          '@field_name' => $field_name,
          '@langname' => $langname,
        ]),
      ];
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(Iteminterface $item) : void {
    $entity = $item->getOriginalObject()?->getValue();
    $parentType = $entity->get('parent_type')->value;
    $parentId = $entity->get('parent_id')->value;

    if ($parentType === NULL || $parentId === NULL) {
      return;
    }

    $parentEntity = $this->entityTypeManager->getStorage($parentType)->load($parentId);

    if (!$parentEntity instanceof ContentEntityInterface) {
      return;
    }

    foreach ($item->getFields() as $field) {
      $indexableValue = NULL;
      $propertyPath = $field->getPropertyPath();

      switch ($propertyPath) {
        case 'parent_url':
          $indexableValue = $parentEntity->toUrl(NULL, ['absolute' => TRUE])->toString();
          break;

        case 'parent_title_fi':
          $indexableValue = $this->getParentTitle($parentEntity, 'fi');
          break;

        case 'parent_title_sv':
          $indexableValue = $this->getParentTitle($parentEntity, 'sv');
          break;

        case 'parent_title_en':
          $indexableValue = $this->getParentTitle($parentEntity, 'en');
          break;

        case 'parent_image_url':
          $indexableValue = $this->getParentImageUrl($parentEntity);
          break;

        case 'parent_image_alt_fi':
          $indexableValue = $this->getParentImageAlt($parentEntity, 'fi');
          break;

        case 'parent_image_alt_sv':
          $indexableValue = $this->getParentImageAlt($parentEntity, 'sv');
          break;

        case 'parent_image_alt_en':
          $indexableValue = $this->getParentImageAlt($parentEntity, 'en');
          break;

        case 'parent_published_at':
          $indexableValue = $this->getParentPublishedDate($parentEntity);
          break;

      }

      if ($indexableValue === NULL) {
        continue;
      }

      $field->addValue($indexableValue);
    }
  }

  /**
   * Get parent title for a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parentEntity
   *   The parent entity.
   * @param string $langcode
   *   The language code.
   *
   * @return string|null
   *   The parent title or NULL if no translation is found.
   */
  private function getParentTitle(ContentEntityInterface $parentEntity, string $langcode) : ?string {
    $shortTitle = '';

    if (!$parentEntity->hasTranslation($langcode)) {
      return NULL;
    }

    $translation = $parentEntity->getTranslation($langcode);

    if ($translation->hasField('field_short_title')) {
      $shortTitle = $translation->get('field_short_title')->value;
    }

    return !empty($shortTitle) ? $shortTitle : $translation->label();
  }

  /**
   * Get parent image url.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parentEntity
   *   The parent entity.
   *
   * @return string|null
   *   The parent image url or NULL if no image is found.
   */
  private function getParentImageUrl(ContentEntityInterface $parentEntity) : ?string {
    if (!$parentEntity->hasField('field_main_image')) {
      return NULL;
    }

    $media = $parentEntity->get('field_main_image')->entity;
    if (!$media instanceof MediaInterface) {
      return NULL;
    }

    $fid = $media->getSource()->getSourceFieldValue($media);
    $file = $fid ? File::load($fid) : NULL;

    if (!$file instanceof FileInterface) {
      return NULL;
    }

    $url = $file->createFileUrl();
    return $url ?? NULL;
  }

  /**
   * Get parent image alt for a given language.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parentEntity
   *   The parent entity.
   * @param string $langcode
   *   The language code.
   *
   * @return string|null
   *   The parent image alt or NULL if no image alt is found.
   */
  private function getParentImageAlt(ContentEntityInterface $parentEntity, string $langcode) : ?string {
    if (!$parentEntity->hasField('field_main_image')) {
      return NULL;
    }

    $media = $parentEntity->get('field_main_image')->entity;
    if (!$media instanceof MediaInterface) {
      return NULL;
    }

    // Use the translation if available, but continue with the default
    // language if no translation is found.
    if ($media->hasTranslation($langcode)) {
      $media = $media->getTranslation($langcode);
    }

    $sourceField = $media->getSource()->getConfiguration()['source_field'];
    $field = $media->get($sourceField)->first();

    if (!$field instanceof FieldItemInterface) {
      return NULL;
    }

    $alt = $field->get('alt')->getValue();
    return $alt ?? NULL;
  }

  /**
   * Get parent published date.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $parentEntity
   *   The parent entity.
   *
   * @return string|null
   *   The parent published date timestamp or NULL if no published date is
   *   found.
   */
  private function getParentPublishedDate(ContentEntityInterface $parentEntity) : ?string {
    // Only populate this for news items.
    if (in_array($parentEntity->bundle(), ['news_item', 'news_article'])) {
      if ($parentEntity->hasField('published_at')) {
        return $parentEntity->get('published_at')->value;
      }
    }

    return NULL;
  }

}
