<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_tpr_config\Entity\UnitContactCard;
use Drupal\helfi_tpr_config\Entity\Unit;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the unit contact card paragraph bundle class.
 *
 * @group helfi_tpr_config
 */
class UnitContactCardTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'allowed_formats',
    'content_lock',
    'content_translation',
    'entity_reference_revisions',
    'field',
    'file',
    'helfi_media',
    'helfi_tpr',
    'helfi_tpr_config',
    'image',
    'language',
    'link',
    'linkit',
    'media',
    'media_library',
    'menu_link_content',
    'metatag',
    'metatag_open_graph',
    'metatag_twitter_cards',
    'options',
    'paragraphs',
    'paragraphs_library',
    'responsive_image',
    'readonly_field_widget',
    'system',
    'taxonomy',
    'telephone',
    'text',
    'token',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();


    $this->installConfig(['system', 'paragraphs']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('tpr_unit');

    $this->installConfig([
      'helfi_tpr_config',
    ]);
  }

  /**
   * Tests the Unit contact card paragraph bundle behavior.
   */
  public function testUnitContactCard(): void {
    // Create a unit entity.
    $unit = Unit::create([
      'id' => 'test-unit',
      'type' => 'tpr_unit',
      'www' => 'https://example.com',
      'name_override' => 'Test Unit Name',
    ]);
    $unit->save();

    // Create Unit contact card paragraph with all relevant fields.
    $paragraph = UnitContactCard::create([
      'type' => 'unit_contact_card',
      'field_unit_contact_heading' => 'Test title',
      'field_unit_contact_unit' => [['target_id' => $unit->id()]],
    ]);
    $paragraph->save();

    $this->assertInstanceOf(UnitContactCard::class, $paragraph);

    $this->assertEquals('Test title', $paragraph->get('field_unit_contact_heading')->value);

    $label = $paragraph->getAriaLabel();
    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals(
      'See more details of Test Unit Name',
      (string) $label
    );
  }

}
