<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tpr_config\Kernel\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_tpr_config\Entity\UnitContactCard;
use Drupal\helfi_tpr_config\Entity\Unit;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

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
    'content_translation',
    'entity_reference_revisions',
    'field',
    'field_group',
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
    'node',
    'options',
    'paragraphs',
    'paragraphs_library',
    'responsive_image',
    'imagecache_external',
    'readonly_field_widget',
    'select2',
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
    $this->installEntitySchema('node');
    $this->installEntitySchema('media');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('tpr_unit');

    ParagraphsType::create([
      'id' => 'unit_contact_card',
      'label' => 'Unit Contact Card',
    ])->save();

    // Add field_unit_contact_unit.
    FieldStorageConfig::create([
      'field_name' => 'field_unit_contact_unit',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'tpr_unit',
      ],
    ])->save();

    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('paragraph', 'field_unit_contact_unit'),
      'bundle' => 'unit_contact_card',
      'entity_type' => 'paragraph',
      'label' => 'Unit reference',
      'settings' => [
        'handler' => 'default:tpr_unit',
      ],
    ])->save();
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

    /** @var \Drupal\helfi_tpr_config\Entity\UnitContactCard $paragraph */
    $paragraph = UnitContactCard::create([
      'type' => 'unit_contact_card',
      'field_unit_contact_unit' => [['target_id' => $unit->id()]],
    ]);
    $paragraph->save();

    $this->assertInstanceOf(UnitContactCard::class, $paragraph);

    $label = $paragraph->getAriaLabel();
    $this->assertInstanceOf(TranslatableMarkup::class, $label);
    $this->assertEquals(
      'See more details of Test Unit Name',
      (string) $label
    );
  }

}
