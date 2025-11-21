<?php

/**
 * @file
 * Contains token data for helfi api base.
 */

declare(strict_types=1);

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Implements hook_token_info().
 */
function helfi_platform_config_token_info() : array {
  $info['tokens']['site']['shareable-image'] = [
    'name' => t('Default OG Image'),
    'description' => t('Default OG image is used as a default thumbnail in social networks and other services.'),
  ];
  $info['tokens']['node']['shareable-image'] = [
    'name' => t('Shareable image'),
    'description' => t('Shareable image is used as a thumbnail in social networks and other services.'),
  ];
  $info['tokens']['site']['page-title-suffix'] = [
    'name' => t('Page title suffix'),
    'description' => t('Official suffix for page title.'),
  ];
  $info['tokens']['node']['display-title'] = [
    'name' => t('Display title'),
    'description' => t('Display title will try to use the hero paragraph title if it exists. If not, it will use the node title.'),
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
 *
 * @see \Drupal\helfi_platform_config\Token\OGImageManager
 */
function helfi_platform_config_tokens(
  $type,
  $tokens,
  array $data,
  array $options,
  BubbleableMetadata $bubbleable_metadata,
) : array {
  $replacements = [];

  foreach ($tokens as $name => $original) {
    if ($name === 'shareable-image') {
      $entity = $data[$type] ?? NULL;

      if ($entity === NULL || $entity instanceof EntityInterface) {
        /** @var \Drupal\helfi_platform_config\Token\OGImageManager $image_manager */
        $image_manager = \Drupal::service('helfi_platform_config.og_image_manager');

        $replacements[$original] = $image_manager->buildUrl($entity);
      }
    }
    elseif ($name === 'page-title-suffix') {
      $language = Drupal::languageManager()
        ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);

      $replacements[$original] = match ($language->getId()) {
        'fi' => 'Helsingin kaupunki',
        'sv' => 'Helsingfors stad',
        'ru' => 'Гopoд Xeльcинки',
        default => 'City of Helsinki',
      };
    }
    // Custom token for lead in.
    elseif ($name === 'lead-in' && !empty($data['node'])) {
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
