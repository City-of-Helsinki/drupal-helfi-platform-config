<?php

declare(strict_types=1);

namespace Drupal\helfi_recommendations\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\taxonomy\TermInterface;

/**
 * A trait for shared Suggested topics functionality.
 */
trait SuggestedTopicsTrait {

  /**
   * {@inheritDoc}
   */
  public function setParentEntity(EntityInterface $parent): self {
    /** @var \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver */
    $environmentResolver = \Drupal::service(EnvironmentResolverInterface::class);

    $project = NULL;
    try {
      $project = $environmentResolver->getActiveProject()->getName();
    }
    catch (\InvalidArgumentException) {
    }

    $this->set('parent_id', $parent->id());
    $this->set('parent_type', $parent->getEntityTypeId());
    $this->set('parent_bundle', $parent->bundle());
    $this->set('parent_instance', $project);

    return $this;
  }

  /**
   * Returns an array of base field definitions for Suggested topics.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   The fields.
   */
  public static function suggestedTopicsFields(EntityTypeInterface $entity_type) : array {
    if (!is_subclass_of($entity_type->getClass(), SuggestedTopicsInterface::class)) {
      throw new UnsupportedEntityTypeDefinitionException('The entity type ' . $entity_type->id() . ' does not implement ' . SuggestedTopicsInterface::class);
    }
    $fields = [];

    $fields['parent_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent ID'))
      ->setDescription(t('The ID of the parent entity of which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE);

    $fields['parent_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent type'))
      ->setDescription(t('The entity parent type to which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['parent_bundle'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent bundle'))
      ->setDescription(t('The entity parent bundle to which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    $fields['parent_translations'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent translations'))
      ->setDescription(t('The entity parent published translation languages to which this entity is referenced.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['parent_instance'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent instance'))
      ->setDescription(t('The name of the instance where this entity is located at.'))
      ->setSetting('is_ascii', TRUE);

    $fields['keywords'] = BaseFieldDefinition::create('scored_entity_reference')
      ->setLabel(new TranslatableMarkup('Keywords'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'taxonomy_term')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritDoc}
   */
  public function hasKeywords(): bool {
    return !$this->get('keywords')->isEmpty();
  }

  /**
   * {@inheritDoc}
   */
  public function getKeywords(): array {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $termStorage = $entityTypeManager->getStorage('taxonomy_term');

    $keywords = [];

    $field = $this->get('keywords');
    assert($field instanceof EntityReferenceFieldItemListInterface);
    foreach ($field->getValue() as $keyword) {
      $term = $termStorage->load($keyword['target_id']);
      if ($term instanceof TermInterface) {
        $keywords[] = [
          'label' => $term->label(),
          'score' => $keyword['score'],
        ];
      }
    }

    return $keywords;
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTagsToInvalidate(): array {
    return Cache::mergeTags(parent::getCacheTagsToInvalidate(), ...$this->getKeywordsCacheTags());
  }

  /**
   * Get the cache tags for all the keywords.
   *
   * @return array
   *   Array of cache tags for keywords.
   */
  protected function getKeywordsCacheTags(): array {
    $tags = [];

    $field = $this->get('keywords');
    assert($field instanceof EntityReferenceFieldItemListInterface);
    foreach ($field->referencedEntities() as $keyword) {
      $tags[] = $keyword->getCacheTags();
    }

    return $tags;
  }

}
