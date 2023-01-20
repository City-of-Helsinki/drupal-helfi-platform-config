<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_paragraphs_contact_card_listing\Functional;

use Drupal\Tests\helfi_platform_config\Functional\ParagraphTestBase;

/**
 * Tests helfi_paragraphs_contact_card_listing module.
 *
 * @group helfi_platform_config
 */
class ContactCardListingParagraphsTest extends ParagraphTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_paragraphs_contact_card_listing',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests paragraph.
   */
  public function testParagraphs() : void {
    $this->assertFrontPageLanguages();

    // Make sure contact card is enabled for contact card listing paragraph type
    // and social media link paragraph is enabled for contact card paragraph.
    $this->assertParagraphTypeEnabled();
  }

  /**
   * {@inheritdoc}
   */
  protected function getParagraphTypes(): array {
    return helfi_paragraphs_contact_card_listing_helfi_paragraph_types();
  }

}
