<?php

declare(strict_types=1);

namespace Drupal\helfi_users\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactory;
use Drupal\helfi_users\TranslationOutdatedManager;
use Drupal\node\NodeInterface;

/**
 * Translation-related hook implementations for helfi_users.
 */
class TranslationHooks {

  use AutowireTrait;

  public function __construct(
    private readonly TranslationOutdatedManager $translationOutdatedManager,
    private readonly Connection $database,
    private readonly QueueFactory $queueFactory,
  ) {}

  /**
   * Implements hook_preprocess_views_view_fields__dashboard_your_content().
   */
  #[Hook('preprocess_views_view_fields__dashboard_your_content')]
  public function preprocessDashboardYourContentFields(array &$variables): void {
    $row = $variables['view']->result[$variables['row']->index] ?? NULL;
    $entity = $row?->_entity;

    if (!$entity instanceof NodeInterface) {
      return;
    }

    if ($entity->get('content_translation_outdated')->value) {
      $variables['attributes']['class'][] = 'translation-outdated';
    }
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface) {
      return;
    }

    $originalLangcode = $entity->getUntranslated()->language()->getId();
    $savedLangcode = $entity->language()->getId();

    if ($savedLangcode === $originalLangcode) {
      return;
    }

    $translation = $entity->getTranslation($savedLangcode);
    if ($translation->get('content_translation_outdated')->value) {
      $translation->set('content_translation_outdated', 0);
    }
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $nids = $this->database->select('node_field_data', 'n')
      ->fields('n', ['nid'])
      ->groupBy('n.nid')
      ->having('COUNT(DISTINCT n.langcode) > 1')
      ->execute()
      ->fetchCol();

    $queue = $this->queueFactory->get('translation_outdated_check');
    foreach ($nids as $nid) {
      $queue->createItem(['nid' => $nid]);
    }
  }

}
