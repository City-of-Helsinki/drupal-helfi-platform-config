<?php

/**
 * @file
 * Contains token data for helfi api base.
 */

declare(strict_types=1);

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_paragraphs_hero\Entity\Hero;

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
) : array {
  $replacements = [];

  foreach ($tokens as $name => $original) {
    switch ($name) {
      // Default OG image.
      case 'shareable-image':
        $entity = $data[$type] ?? NULL;

        if ($entity === NULL || $entity instanceof EntityInterface) {
          /** @var \Drupal\helfi_platform_config\Token\OGImageManager $image_manager */
          $image_manager = \Drupal::service('helfi_platform_config.og_image_manager');

          $replacements[$original] = $image_manager->buildUrl($entity);
        }
        break;

      // Page title suffix; [node:display-title] | [page-title-suffix].
      // For example: Example node title | City of Helsinki.
      case 'page-title-suffix':
        $language = Drupal::languageManager()
          ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);

        $replacements[$original] = match ($language->getId()) {
          'fi' => 'Helsingin kaupunki',
          'sv' => 'Helsingfors stad',
          'ru' => 'Гopoд Xeльcинки',
          default => 'City of Helsinki',
        };
        break;

      // Node display title and lead in.
      case 'display-title':
      case 'lead-in':
        if (!empty($data['node'])) {
          /** @var \Drupal\node\NodeInterface $node */
          $node = $data['node'];
          $hero = NULL;

          // Check if hero paragraph and hero paragraph description exists.
          if (
            $node->hasField('field_hero') &&
            !$node->get('field_hero')?->first()?->isEmpty()
          ) {
            // Get hero paragraph.
            /** @var \Drupal\helfi_paragraphs_hero\Entity\Hero $hero */
            $hero = $node->get('field_hero')
              ?->first()
              ?->get('entity')
              ?->getTarget()
              ?->getValue();
          }

          // Handle node (landing page and basic page) display title.
          if ($name === 'display-title') {
            // Default to node title.
            $title = $node->getTitle();

            // Use hero paragraph title as the title if it exists.
            if (
              $hero instanceof Hero &&
              $hero->hasField('field_hero_title') &&
              !$hero->get('field_hero_title')->isEmpty()
            ) {
              $title = $hero->get('field_hero_title')->value;
            }
            $replacements[$original] = $title;
          }

          // Handle lead in.
          if ($name === 'lead-in') {
            $lead_in_text = '';

            // Check if lead in field exists and use it if it exists.
            if (
              $node->hasField('field_lead_in') &&
              !$node?->get('field_lead_in')?->isEmpty()
            ) {
              $lead_in_text = $node->get('field_lead_in')->value;
            }

            // Check if hero paragraph description exists
            // and use it if it exists.
            if (
              $hero instanceof Hero &&
              $hero->hasField('field_hero_desc') &&
              !$hero->get('field_hero_desc')->isEmpty()
            ) {
              $lead_in_text = $hero->get('field_hero_desc')->value;
            }

            // Add lead in text to replacements.
            if (!empty($lead_in_text)) {
              $replacements[$original] = $lead_in_text;
            }
          }
        }
        break;

      default:
        break;
    }
  }

  return $replacements;
}
