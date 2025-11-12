<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_hero\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Bundle class for Hero -paragraph.
 */
class Hero extends Paragraph implements ParagraphInterface {

  use StringTranslationTrait;

  /**
   * Get paragraph design.
   *
   * @return string
   *   The design.
   */
  public function getDesign(): string {
    return $this->get('field_hero_design')->value;
  }

  /**
   * Get the Hero image.
   *
   * @return \Drupal\media\Entity\Media|false
   *   Returns the media entity or false if not found.
   */
  public function getImage(): Media|false {
    if ($this->get('field_hero_image')->isEmpty()) {
      return FALSE;
    }

    try {
      $media = FALSE;
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItemInterface $hero_image */
      $hero_image = $this->get('field_hero_image')->first();
      if ($hero_image instanceof EntityReferenceItem) {
        /** @var \Drupal\Core\Entity\Plugin\DataType\EntityReference $media */
        $entity_reference = $hero_image->get('entity');
        /** @var \Drupal\media\Entity\Media $media */
        $media = $entity_reference->getValue();

        // If the media has a translation, use it.
        if ($media->hasTranslation($this->language()->getId())) {
          $media = $media->getTranslation($this->language()->getId());
        }
      }
      return $media;
    }
    catch (MissingDataException $e) {
      return FALSE;
    }
  }

  /**
   * Get the image author if any.
   *
   * @return string|false
   *   The image author as a string or false.
   */
  public function getImageAuthor(): string|FALSE {
    $image = $this->getImage();
    if (!$image || $image->get('field_photographer')->isEmpty()) {
      return FALSE;
    }

    try {
      $image_author = $image->get('field_photographer')->first()->getString();
      return $image_author;
    }
    catch (MissingDataException $e) {
      return FALSE;
    }
  }

  /**
   * Gets the paragraph title.
   *
   * @return string
   *   The title.
   */
  public function getTitle() : string {
    return $this->get('field_hero_title')->value;
  }

  /**
   * Gets the paragraph description.
   *
   * @return string
   *   The description.
   */
  public function getDescription() : string {
    return $this->get('field_hero_desc')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    if ($this->getDesign() == 'without-image-left') {
      $this->set('field_hero_image', NULL);
    }
    parent::preSave($storage);
  }

}
