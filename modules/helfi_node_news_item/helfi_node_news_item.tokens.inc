<?php

/**
 * @file
 * Contains token data for HELfi News item.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function helfi_node_news_item_token_info(): array {

  $info['tokens']['node']['short-title'] = [
    'name' => t('Short title'),
    'description' => t('Short title is used in news listings, lift-ups and social media shares. When a short title is entered, it overrides normal title field everywhere except in the news item itself.'),
  ];
  return $info;
}

/**
 * Implements hook_tokens().
 */
function helfi_node_news_item_tokens(
  $type,
  $tokens,
  array $data,
  array $options,
  BubbleableMetadata $bubbleable_metadata,
): array {
  $replacements = [];

  foreach ($tokens as $name => $original) {
    // Handle short-title token.
    if ($name === 'short-title' && !empty($data['node'])) {
      /** @var \Drupal\node\Entity\Node $node */
      $node = $data['node'];
      $short_title = $node->getTitle();

      // If node has short title set, use it as short-title.
      if (
        $node->hasField('field_short_title') &&
        !$node->field_short_title->isEmpty()
      ) {
        $short_title = $node->field_short_title->value;
      }

      $replacements[$original] = $short_title;
    }
  }

  return $replacements;
}
