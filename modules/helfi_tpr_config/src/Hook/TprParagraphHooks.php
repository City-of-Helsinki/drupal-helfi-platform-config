<?php

declare(strict_types=1);

namespace Drupal\helfi_tpr_config\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\helfi_platform_config\DTO\ParagraphTypeCollection;

/**
 * Paragraph type hooks for helfi_tpr_config module.
 */
class TprParagraphHooks {

  /**
   * Implements hook_helfi_paragraph_types().
   *
   * @return \Drupal\helfi_platform_config\DTO\ParagraphTypeCollection[]
   *   The enabled paragraph types.
   */
  #[Hook('helfi_paragraph_types')]
  public static function helfiParagraphTypes() : array {
    $entities = [
      'tpr_unit' => [
        'tpr_unit' => [
          'field_banner' => [
            'banner' => 0,
          ],
          'field_content' => [
            'text' => 0,
            'accordion' => 1,
            'banner' => 2,
            'image' => 3,
            'list_of_links' => 4,
            'content_cards' => 5,
            'from_library' => 6,
            'remote_video' => 7,
            'columns' => 8,
            'contact_card_listing' => 9,
            'image_gallery' => 10,
          ],
          'field_lower_content' => [
            'list_of_links' => 0,
            'content_cards' => 1,
            'event_list' => 2,
            'contact_card_listing' => 3,
            'news_list' => 4,
            'from_library' => 5,
            'banner' => 6,
            'accordion' => 7,
            'text' => 8,
            'columns' => 9,
            'image' => 10,
            'liftup_with_image' => 11,
            'map' => 12,
            'remote_video' => 13,
            'image_gallery' => 14,
          ],
        ],
      ],
      'tpr_service' => [
        'tpr_service' => [
          'field_banner' => [
            'banner' => 0,
          ],
          'field_content' => [
            'text' => 0,
            'accordion' => 1,
            'banner' => 2,
            'image' => 3,
            'list_of_links' => 4,
            'content_cards' => 5,
            'from_library' => 6,
            'phasing' => 7,
            'map' => 8,
            'remote_video' => 9,
            'columns' => 10,
            'event_list' => 11,
            'contact_card_listing' => 12,
            'unit_accessibility_information' => 13,
            'unit_contact_card' => 14,
            'image_gallery' => 15,
          ],
          'field_sidebar_content' => [
            'from_library' => 0,
            'sidebar_text' => 1,
          ],
          'field_lower_content' => [
            'list_of_links' => 0,
            'content_cards' => 1,
            'event_list' => 2,
            'contact_card_listing' => 3,
            'news_list' => 4,
            'from_library' => 5,
            'banner' => 6,
            'accordion' => 7,
            'text' => 8,
            'columns' => 9,
            'image' => 10,
            'liftup_with_image' => 11,
            'map' => 12,
            'remote_video' => 13,
            'phasing' => 14,
            'unit_accessibility_information' => 15,
            'unit_contact_card' => 16,
            'image_gallery' => 17,
          ],
        ],
      ],
      'node' => [
        'page' => [
          'field_content' => [
            'service_list_search' => 16,
            'unit_search' => 17,
            'unit_contact_card' => 18,
          ],
          'field_lower_content' => [
            'service_list_search' => 16,
            'unit_search' => 17,
            'unit_contact_card' => 18,
          ],
        ],
        'landing_page' => [
          'field_content' => [
            'service_list' => 15,
            'service_list_search' => 16,
            'unit_search' => 17,
            'unit_contact_card' => 18,
          ],
        ],
      ],
      'paragraphs_library_item' => [
        'paragraphs_library_item' => [
          'paragraphs' => [
            'unit_search' => 0,
            'service_list' => 0,
            'unit_contact_card' => 0,
          ],
        ],
      ],
    ];

    $enabled = [];
    foreach ($entities as $entityTypeId => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        foreach ($fields as $field => $paragraphTypes) {
          foreach ($paragraphTypes as $paragraphType => $weight) {
            $enabled[] = new ParagraphTypeCollection($entityTypeId, $bundle, $field, $paragraphType, $weight);
          }
        }
      }
    }
    return $enabled;
  }

}
