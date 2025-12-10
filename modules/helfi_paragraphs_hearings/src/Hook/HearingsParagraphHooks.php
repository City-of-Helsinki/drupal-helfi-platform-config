<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_hearings\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity\BundleFieldDefinition;
use Drupal\helfi_platform_config\DTO\ParagraphTypeCollection;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Hooks for helfi_paragraphs_hearings module.
 */
class HearingsParagraphHooks {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('paragraph_view')]
  public function view(
    array &$build,
    ParagraphInterface $entity,
    EntityViewDisplayInterface $display,
    string $view_mode,
  ): void {
    if ($entity->bundle() !== 'hearings') {
      return;
    }

    if ($display->getComponent('list')) {
      $storage = $this->entityTypeManager
        ->getStorage('helfi_hearings');

      $entities = $storage->loadMultiple();

      $cache = new CacheableMetadata();

      if (!$entities) {
        // Retries request every minute if no hearings are found.
        $cache->setCacheMaxAge(60);
      }

      foreach ($entities as $item) {
        // See 'persistent_cache_max_age' for the external entity type.
        $cache->addCacheableDependency($item);

        $build['list'][] = $this->entityTypeManager
          ->getViewBuilder('helfi_hearings')
          ->view($item);
      }

      $cache->applyTo($build);
    }
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public static function entityExtraFieldInfo() : array {
    $extra = [];
    $extra['paragraph']['hearings']['display']['list'] = [
      'label' => new TranslatableMarkup('List of hearings'),
      'description' => new TranslatableMarkup('The value for this field is defined in %hook hook.', [
        '%hook' => 'helfi_paragraphs_hearings_paragraph_view()',
      ]),
      'weight' => 100,
      'visible' => TRUE,
    ];

    return $extra;
  }

  /**
   * Implements hook_helfi_paragraph_types().
   */
  #[Hook('helfi_paragraph_types')]
  public static function helfiParagraphTypes() : array {
    $types = [
      'field_content' => [
        'hearings' => 14,
      ],
    ];

    $enabled = [];
    foreach ($types as $field => $paragraphTypes) {
      foreach ($paragraphTypes as $paragraphType => $weight) {
        $enabled[] = new ParagraphTypeCollection('node', 'landing_page', $field, $paragraphType, $weight);
      }
    }
    return $enabled;
  }

  /**
   * Implements hook_entity_bundle_field_info_alter().
   */
  #[Hook('entity_bundle_field_info_alter')]
  public static function entityBundleFieldInfoAlter(
    &$fields,
    EntityTypeInterface $entity_type,
    $bundle,
  ) : void {
    if ($entity_type->id() === 'helfi_hearings') {
      $fields['main_image'] = BundleFieldDefinition::create('link')
        ->setName('main_image')
        ->setLabel(new TranslatableMarkup('Main image'))
        ->setTargetEntityTypeId($entity_type->id())
        ->setTargetBundle($bundle)
        ->setSettings([
          'max_length' => 1024,
        ])
        ->setDisplayConfigurable('view', TRUE);

      // Additional entity info fields.
      $entity_info_fields = [
        'close_at' => new TranslatableMarkup('Close at'),
        'created_at' => new TranslatableMarkup('Created at'),
        'open_at' => new TranslatableMarkup('Open at'),
        'slug' => new TranslatableMarkup('Slug'),
        'comments' => new TranslatableMarkup('Comments'),
        'organization' => new TranslatableMarkup('Organization'),
        'abstract' => new TranslatableMarkup('Abstract'),
        'main_image_title' => new TranslatableMarkup('Main image title'),
        'url' => new TranslatableMarkup('Url'),
        'count' => new TranslatableMarkup('Count'),
        'langcode' => new TranslatableMarkup('Langcode'),
        'existing_translations' => new TranslatableMarkup('Existing translations'),
      ];

      foreach ($entity_info_fields as $field_name => $field_label) {
        $fields[$field_name] = BundleFieldDefinition::create('string')
          ->setName($field_name)
          ->setLabel($field_label)
          ->setDisplayConfigurable('view', TRUE)
          ->setDisplayConfigurable('form', TRUE);
      }
    }
  }

}
