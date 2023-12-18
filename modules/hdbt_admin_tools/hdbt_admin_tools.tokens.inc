<?php

/**
 * @file
 * Contains token data for helfi admin tools.
 */

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Implements hook_token_info().
 */
function hdbt_admin_tools_token_info() {

  $info['tokens']['site']['default-og-image'] = [
    'name' => t('Default OG Image'),
    'description' => t('Default OG image is used as a default thumbnail in social networks and other services.'),
  ];

  $info['tokens']['node']['shareable-image'] = [
    'name' => t('Shareable image'),
    'description' => t('Shareable image is used as a thumbnail in social networks and other services.'),
  ];

  $info['tokens']['node']['lead-in'] = [
    'name' => t('Lead in'),
    'description' => t(
      'Lead in will try to use the hero paragraph description if it exists. If not, it will use the node lead in field.'
    ),
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function hdbt_admin_tools_tokens(
  $type,
  $tokens,
  array $data,
  array $options,
  BubbleableMetadata $bubbleable_metadata
) {
  $replacements = [];

  foreach ($tokens as $name => $original) {
    $default_image = '';

    /** @var Drupal\Core\Extension\ThemeHandler $theme_handler */
    $theme_handler = Drupal::service('theme_handler');

    // Add default og-image as the shareable image.
    if ($theme_handler->themeExists('hdbt')) {
      $theme = $theme_handler->getTheme('hdbt');
      $current_language = \Drupal::languageManager()
        ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
      $image_file_name = $current_language === 'sv' ? 'og-global-sv.png' : 'og-global.png';

      /** @var \Drupal\Core\File\FileUrlGeneratorInterface $service */
      $service = \Drupal::service('file_url_generator');
      $default_image = $service->generate("{$theme->getPath()}/src/images/{$image_file_name}")
        ->toString(TRUE)
        ->getGeneratedUrl();
    }

    // Custom token for default-og-image.
    if ($name === 'default-og-image') {
      $replacements[$original] = $default_image;
    }

    // Handle fallback image for TPR Unit.
    if ($name === 'picture' && $type === 'tpr_unit' && !empty($data[$type])) {
      /** @var \Drupal\helfi_tpr\Entity\Unit $entity */
      $entity = $data[$type];
      $replacements[$original] = $entity->getPictureUrl() ?? $default_image;
    }

    // Custom token for shareable-image.
    if ($name === 'shareable-image' && !empty($data['node'])) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $data['node'];
      $image_url = $default_image;

      $image_style = ImageStyle::load('og_image');

      if (
        $node->hasField('field_liftup_image') &&
        isset($node->field_liftup_image->entity) &&
        $node->field_liftup_image->entity instanceof MediaInterface &&
        $node->field_liftup_image->entity->hasField('field_media_image')
      ) {
        // If liftup image has an image set, use it as the shareable image.
        $image_entity = $node->field_liftup_image->entity->field_media_image;
        $image_path = $image_entity->entity->getFileUri();
        $image_url = $image_style->buildUrl($image_path);
      }
      elseif (
        $node->hasField('field_image') &&
        isset($node->field_image->entity) &&
        $node->field_image->entity instanceof MediaInterface &&
        $node->field_image->entity->hasField('field_media_image')
      ) {
        // If the node has an image, use that.
        $image_entity = $node->field_image->entity->field_media_image;
        $image_path = $image_entity->entity->getFileUri();
        $image_url = $image_style->buildUrl($image_path);
      }
      elseif (
        $node->hasField('field_organization') &&
        $node->get('field_organization')?->entity?->hasField('field_default_image') &&
        !$node->get('field_organization')->entity->get('field_default_image')->isEmpty()
      ) {
        // Use the image from the taxonomy term.
        $taxonomy_term = $node->field_organization->entity;
        $image_entity = $taxonomy_term->field_default_image;
        $image_path = $image_entity->entity->getFileUri();
        $image_url = $image_style->buildUrl($image_path);
      }

      $replacements[$original] = $image_url;
    }

    // Custom token for lead in.
    if ($name === 'lead-in' && !empty($data['node'])) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $data['node'];
      $lead_in_text = '';

      // Check if lead in field exists.
      if (
        $node->hasField('field_lead_in') &&
        !$node?->get('field_lead_in')?->isEmpty()
      ) {
        // Use lead in field as lead in text.
        $lead_in_text = $node->get('field_lead_in')->value;
      }

      // Check if hero paragraph and hero paragraph description exists.
      if (
        $node->hasField('field_hero') &&
        !$node->get('field_hero')?->first()?->isEmpty()
      ) {
        // Get hero paragraph.
        $hero = $node->get('field_hero')
          ?->first()
          ?->get('entity')
          ?->getTarget()
          ?->getValue();

        if (
          $hero instanceof ParagraphInterface &&
          $hero->hasField('field_hero_desc') &&
          !$hero->get('field_hero_desc')->isEmpty()
        ) {
          // Use hero paragraph description as lead in text.
          $lead_in_text = $hero->get('field_hero_desc')->value;
        }
      }

      // Add lead in text to replacements.
      $replacements[$original] = $lead_in_text;
    }
  }

  return $replacements;
}
