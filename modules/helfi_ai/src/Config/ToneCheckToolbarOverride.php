<?php

declare(strict_types=1);

namespace Drupal\helfi_ai\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Add the AI tone check button to editor toolbars.
 */
final class ToneCheckToolbarOverride implements ConfigFactoryOverrideInterface {

  /**
   * Editor formats.
   */
  private const array EDITORS = [
    'editor.editor.full_html',
    'editor.editor.minimal',
  ];

  /**
   * A state for the tone check feature.
   */
  private ?bool $toneCheckEnabled = NULL;

  public function __construct(
    private readonly StorageInterface $configStorage,
    private readonly AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * @param string[] $names
   *   The config names.
   *
   * @return array<string, mixed>
   *   The config overrides.
   */
  public function loadOverrides($names) : array {
    $overrides = [];
    $targets = array_intersect($names, self::EDITORS);

    if (!$targets || !$this->toneCheckEnabled()) {
      return $overrides;
    }

    foreach ($targets as $name) {
      $items = $this->configStorage->read($name)['settings']['toolbar']['items'] ?? NULL;

      // Skip editors without a toolbar or with the button already present.
      if (!is_array($items) || in_array('aiToneCheck', $items, TRUE)) {
        continue;
      }
      $overrides[$name]['settings']['toolbar']['items'] = $this->addToneCheck($items);
    }
    return $overrides;
  }

  /**
   * Check whether the tone check feature is enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  private function toneCheckEnabled() : bool {
    if ($this->toneCheckEnabled === NULL) {
      $isEnabled = (bool) ($this->configStorage->read('helfi_ai.settings')['enable_tone_check'] ?? FALSE);
      $hasPermission = $this->currentUser->hasPermission('use helfi ai tone check');

      $this->toneCheckEnabled = $isEnabled && $hasPermission;
    }
    return $this->toneCheckEnabled;
  }

  /**
   * Insert the tone check button to the CKEditor toolbar.
   *
   * @param string[] $items
   *   The current toolbar items.
   *
   * @return string[]
   *   The toolbar items with the button added.
   */
  private function addToneCheck(array $items) : array {
    $position = array_search('sourceEditing', $items, TRUE);

    if ($position === FALSE) {
      return [...$items, '|', 'aiToneCheck'];
    }
    // Place the button and separator before source editing.
    array_splice($items, (int) $position, 0, ['aiToneCheck', '|']);
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() : string {
    return 'helfi_ai_tone_check';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) : CacheableMetadata {
    $metadata = new CacheableMetadata();

    if (in_array($name, self::EDITORS, TRUE)) {
      $metadata->addCacheTags(['config:helfi_ai.settings']);
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) : ?StorableConfigBase {
    return NULL;
  }

}
