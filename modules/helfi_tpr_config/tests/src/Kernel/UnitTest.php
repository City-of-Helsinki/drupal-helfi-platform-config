<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\helfi_tpr_config\Entity\Unit;
use Drupal\Core\Url;

/**
 * Tests the helfi_tpr_config Unit bundle class.
 *
 * @covers \Drupal\helfi_tpr_config\Entity\Unit::getWebsiteUrl
 * @group helfi_tpr_config
 */
class UnitTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'link',
    'user',
    'file',
    'media',
    'image',
    'address',
    'menu_link_content',
    'telephone',
    'metatag',
    'metatag_open_graph',
    'metatag_twitter_cards',
    'entity_reference_revisions',
    'paragraphs',
    'paragraphs_library',
    'options',
    'token',
    'helfi_tpr',
    'helfi_tpr_config',
  ];

  /**
   * Set up the test environment.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('tpr_unit');
  }

  /**
   * Tests getWebsiteUrl() with newly created entity.
   */
  public function testGetWebsiteUrl(): void {
    $unit = Unit::create([
      'id' => 'test-unit',
      'type' => 'tpr_unit',
      'www' => 'https://example.com',
    ]);
    $unit->save();

    $url = $unit->getWebsiteUrl();
    $this->assertInstanceOf(Url::class, $url);
    $this->assertEquals('https://example.com', $url->getUri());
  }

}
