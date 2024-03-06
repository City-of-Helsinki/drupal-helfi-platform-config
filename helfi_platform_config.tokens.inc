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
  $info['tokens']['site']['page-title-suffix'] = [
    'name' => t('Page title suffix'),
    'description' => t('Official suffix for page title.'),
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
  BubbleableMetadata $bubbleable_metadata
) : array {
  $replacements = [];

  foreach ($tokens as $name => $original) {
    if ($name === 'shareable-image' && isset($data[$type])) {
      $entity = $data[$type];

      if ($entity instanceof EntityInterface) {
        /** @var \Drupal\helfi_platform_config\Token\OGImageManager $image_manager */
        $image_manager = \Drupal::service('helfi_platform_config.og_image_manager');

        $replacements[$original] = $image_manager->buildUrl($entity);
      }
    }

    if ($name === 'page-title-suffix') {
      $language = Drupal::languageManager()
        ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE);

      $replacements[$original] = match ($language->getId()) {
        'fi' => 'Helsingin kaupunki',
        'sv' => 'Helsingfors stad',
        'ru' => 'Гopoд Xeльcинки',
        default => 'City of Helsinki',
      };
    }
  }

  return $replacements;
}
