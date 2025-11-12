<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_hero\Kernel\Entity;

use Drupal\Tests\helfi_paragraphs_hero\Kernel\KernelTestBase;
use Drupal\file\Entity\File;
use Drupal\helfi_paragraphs_hero\Entity\Hero;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests Hero installation.
 *
 * @coversDefaultClass \Drupal\helfi_paragraphs_hero\Entity\Hero
 * @group helfi_paragraphs_hero
 */
class HeroParagraphTest extends KernelTestBase {

  /**
   * Tests that paragraph uses proper bundle class.
   */
  public function testBundleClass() : void {
    $defaults = [
      'type' => 'hero',
      'field_hero_title' => 'Hero title',
      'field_hero_desc' => 'Hero description',
      'field_hero_image' => NULL,
      'field_hero_design' => 'with-image-left',
    ];

    $paragraph = Paragraph::create($defaults);
    $paragraph->save();

    $this->assertInstanceOf(Hero::class, $paragraph);
    $this->assertEquals($defaults['field_hero_title'], $paragraph->getTitle());
    $this->assertEquals($defaults['field_hero_desc'], $paragraph->getDescription());
    $this->assertEquals($defaults['field_hero_image'], $paragraph->getImage());
    $this->assertEquals($defaults['field_hero_design'], $paragraph->getDesign());
  }

  /**
   * Test the getImageAuthor method when no image exists.
   */
  public function testGetImageAuthorNoImageExists(): void {
    /** @var \Drupal\helfi_paragraphs_hero\Entity\Hero $hero_paragraph */
    $hero_paragraph = Paragraph::create([
      'type' => 'hero',
      'field_hero_image' => NULL,
    ]);
    $this->assertFalse($hero_paragraph->getImageAuthor());
  }

  /**
   * Test the getImageAuthor method when image author is empty.
   */
  public function testGetImageAuthorWhenAuthorEmpty(): void {
    $image = $this->createMediaEntityWithVirtualFile('', 'public://virtual-file.jpg');

    /** @var \Drupal\helfi_paragraphs_hero\Entity\Hero $hero_paragraph */
    $hero_paragraph = Paragraph::create([
      'type' => 'hero',
      'field_hero_image' => [
        'target_id' => $image->id(),
      ],
    ]);
    $this->assertFalse(
      $hero_paragraph->getImageAuthor(),
      'Image exists, but image author is empty, getImageAuthor() returns FALSE.'
    );
  }

  /**
   * Test the getImageAuthor method when image author exists.
   */
  public function testGetImageAuthor(): void {
    $image = $this->createMediaEntityWithVirtualFile('Ken Smith', 'public://virtual-file.jpg');

    /** @var \Drupal\helfi_paragraphs_hero\Entity\Hero $hero_paragraph */
    $hero_paragraph = Paragraph::create([
      'type' => 'hero',
      'field_hero_image' => [
        'target_id' => $image->id(),
      ],
    ]);

    $image_author = $hero_paragraph->getImageAuthor();
    $this->assertIsString(
      $image_author,
      'Image author is returned as string.'
    );
    $this->assertEquals(
      'Ken Smith',
      $image_author,
      'Photo author text is correctly returned.'
    );
  }

  /**
   * Helper function to create a media entity with a virtual file.
   *
   * @param string $photographer
   *   The photographer name.
   * @param string $file_uri
   *   The URI of the virtual file.
   *
   * @return \Drupal\media\Entity\Media
   *   The created media entity.
   */
  protected function createMediaEntityWithVirtualFile(string $photographer, string $file_uri): Media {
    // Create a file entity using a virtual file path.
    $file = File::create([
      'uri' => $file_uri,
      'status' => 1,
    ]);
    $file->save();

    // Create the media entity and associate the file.
    $image = Media::create([
      'bundle' => 'image',
      'status' => TRUE,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'alt text',
      ],
      'field_photographer' => [
        ['value' => $photographer],
      ],
      'langcode' => 'en',
    ]);
    $image->save();
    return $image;
  }

}
