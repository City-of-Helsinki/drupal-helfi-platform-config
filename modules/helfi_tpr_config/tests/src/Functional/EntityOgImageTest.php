<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\file\Entity\File;
use Drupal\helfi_tpr\Entity\Unit;
use Drupal\media\Entity\Media;

/**
 * Tests default og image.
 *
 * @group helfi_tpr_config
 */
class EntityOgImageTest extends BrowserTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config_base',
    'helfi_tpr_config',
  ];

  /**
   * Test og images.
   */
  public function testOgImages() : void {
    $uri = $this->getTestFiles('image')[0]->uri;

    $file = File::create([
      'uri' => $uri,
    ]);
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Custom name',
      'field_media_image' => $file->id(),
    ]);
    $media->save();

    $node = $this->drupalCreateNode([
      'title' => 'title',
      'langcode' => 'fi',
      'bundle' => 'page',
      'status' => 1,
    ]);
    $node->save();

    // Global image style is used when media field is not set.
    $this->drupalGet($node->toUrl('canonical'));
    $this->assertGlobalOgImage('fi');

    // Media is used when 'field_liftup_image' is set.
    $node->set('field_liftup_image', $media->id());
    $node->save();
    $this->drupalGet($node->toUrl('canonical'));
    $this->assertImageStyle();

    $unit = Unit::create([
      'id' => 123,
      'title' => 'title',
      'langcode' => 'sv',
      'bundle' => 'tpr_unit',
    ]);
    $unit->save();

    // Global image style is used when media field is not set.
    $this->drupalGet($unit->toUrl('canonical'));
    $this->assertGlobalOgImage('sv');

    // Picture url override is used.
    $unit->set('picture_url_override', $media->id());
    $unit->save();
    $this->drupalGet($unit->toUrl('canonical'));
    $this->assertImageStyle();
  }

  /**
   * Assert that og_image image style was used.
   */
  private function assertImageStyle() : void {
    $this->assertSession()->elementAttributeContains('css', 'meta[property="og:image"]', 'content', 'styles/1.9_1200w_630h');
  }

  /**
   * Assert that global og image was used.
   *
   * @param string $langcode
   *   Content langcode.
   */
  private function assertGlobalOgImage(string $langcode) : void {
    $og_image_file = match($langcode) {
      'sv' => 'og-global-sv.png',
      default => 'og-global.png',
    };

    $this->assertSession()->elementAttributeContains('css', 'meta[property="og:image"]', 'content', $og_image_file);
    $this->assertSession()->elementAttributeNotContains('css', 'meta[property="og:image"]', 'content', 'styles/1.9_1200w_630h');
  }

}
