<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_etusivu_entities\Plugin\ExternalEntities\StorageClient\Announcements;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Provides an 'Announcements' block.
 */
#[Block(
  id: "announcements",
  admin_label: new TranslatableMarkup("Announcements"),
)]
final class AnnouncementsBlock extends EtusivuEntityBlockBase {

  public const VISIBILITY_ALL_WEIGHT = 0;
  public const VISIBILITY_REGION_WEIGHT = 1;
  public const VISIBILITY_PAGE_WEIGHT = 2;

  /**
   * Entity types supported by announcements and the corresponding field names.
   */
  private const ENTITY_TYPE_FIELDS = [
    'node' => 'field_announcement_content_pages',
    'tpr_unit' => 'field_announcement_unit_pages',
    'tpr_service' => 'field_announcement_service_pages',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getLocalEntities(): array {
    $langcodes = $this->getContentLangcodes();
    $storage = $this->entityTypeManager->getStorage('node');

    // Get all published announcement nodes.
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'announcement')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $langcodes, 'IN');

    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'survey');

    // Query only local nodes.
    if (isset($fields['field_publish_externally'])) {
      $query->condition('field_publish_externally', FALSE);
    }

    return $storage->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  protected function getRemoteEntities(): array {
    $entityStorage = $this->getExternalEntityStorage('helfi_announcements');
    $nodes = [];

    /** @var \Drupal\external_entities\Entity\ExternalEntityInterface $announcement */
    foreach ($entityStorage->loadMultiple() as $announcement) {
      $linkUrl = NULL;
      $linkText = NULL;
      if ($announcement->hasField('announcement_link_text')) {
        $linkText = $announcement->get('announcement_link_text')->value;
        $linkUrl = $announcement->get('announcement_link_url')->value;
      }

      // Create announcement nodes for the block based on external entity data.
      $nodes[] = Node::create([
        'uuid' => $announcement->get('uuid')->value,
        'type' => 'announcement',
        'langcode' => $announcement->get('langcode')->value,
        'body' => Xss::filter($announcement->get('body')->value),
        'title' => Xss::filter($announcement->get('title')->value),
        'status' => $announcement->get('status')->value,
        'field_announcement_title' => $announcement->get('announcement_assistive_technology_close_button_title')->value,
        'field_announcement_type' => $announcement->get('announcement_type')->value,
        'field_announcement_link' => ['uri' => $linkUrl, 'title' => $linkText],
      ]);
    }

    return $nodes;
  }

  /**
   * {@inheritdoc}
   */
  protected function sortEntities(array $local, array $remote): array {
    $currentEntity = $this->getCurrentPageEntity(array_keys(self::ENTITY_TYPE_FIELDS));
    $referenceField = self::ENTITY_TYPE_FIELDS[$currentEntity?->getEntityTypeId()] ?? NULL;

    $localAnnouncements = [];
    $globalAnnouncements = $remote;

    foreach ($local as $announcementNode) {
      assert($announcementNode instanceof FieldableEntityInterface);

      // Check if the announcement should be shown at all pages.
      // Global announcements should be shown on top of all pages.
      if (
        $announcementNode->hasField('field_publish_externally') &&
        $announcementNode->get('field_publish_externally')->value
      ) {
        $globalAnnouncements[] = $announcementNode;
        continue;
      }

      if ($announcementNode->get('field_announcement_all_pages')->value === "1") {
        $localAnnouncements[] = $announcementNode;
        continue;
      }

      if (!empty($referenceField) && $this->hasReference($referenceField, $announcementNode, $currentEntity)) {
        // Add announcement to showed announcements if current page's entity
        // is found from the list of referenced entities.
        $localAnnouncements[] = $announcementNode;
      }
    }

    $this->sortAnnouncements($localAnnouncements);

    return array_merge($globalAnnouncements, $localAnnouncements);
  }

  /**
   * Sort announcements by type/severity and by visibility.
   *
   * @param \Drupal\node\NodeInterface[] $announcements
   *   Array of nodes.
   */
  private function sortAnnouncements(array &$announcements): void {
    if (empty($announcements)) {
      return;
    }

    // Get all possible values for the announcement types.
    $announcementTypeDefinition = $announcements[0]->getFieldDefinitions()['field_announcement_type'];
    $types = options_allowed_values(
      $announcementTypeDefinition->getFieldStorageDefinition(),
    );

    // Map select-list values with numeric weight value.
    $announcementTypeWeights = $this->createAnnouncementWeightMap($types);

    $this->doSort($announcements, $announcementTypeWeights);
  }

  /**
   * Create the map which is used to order the announcements by severity.
   *
   * @param array $announcementTypes
   *   Should return ['notification' => 0, 'attention' => 1, 'alert' => 2].
   *
   * @return int[]|string[]
   *   Array of announcement type keys and weights.
   */
  private function createAnnouncementWeightMap(array $announcementTypes): array {
    return array_flip(array_keys($announcementTypes));
  }

  /**
   * Execute sorting.
   *
   * @param \Drupal\node\NodeInterface[] $announcements
   *   Announcement entities.
   * @param array $announcementTypeWeights
   *   Announcement types ordered by severity.
   */
  private function doSort(array &$announcements, array $announcementTypeWeights): void {
    // Sort by type/severity.
    usort($announcements, function (
      EntityInterface $a,
      EntityInterface $b,
    ) use ($announcementTypeWeights) {
      $weightA = $announcementTypeWeights[$a->get('field_announcement_type')->value];
      $weightB = $announcementTypeWeights[$b->get('field_announcement_type')->value];
      if ($weightA === $weightB) {
        return 0;
      }
      // More urgent announcements render first.
      return $weightA < $weightB ? 1 : -1;
    });

    // Sort by visibility.
    usort($announcements, function (EntityInterface $a, EntityInterface $b) {
      $visibilityA = $this->resolveVisibilityWeight($a);
      $visibilityB = $this->resolveVisibilityWeight($b);
      // Sort visibility only within same type.
      if (
        $a->get('field_announcement_type')->value !== $b->get('field_announcement_type')->value ||
        $visibilityA === $visibilityB
      ) {
        return 0;
      }
      // Page-specific renders before global announcement.
      return $visibilityA < $visibilityB ? 1 : -1;
    });
  }

  /**
   * Return weight for announcement visibility.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $announcement
   *   Announcement entity.
   *
   * @return int
   *   Visibility weight.
   */
  private function resolveVisibilityWeight(ContentEntityInterface $announcement): int {
    if ($announcement->get('field_announcement_all_pages')->value) {
      return self::VISIBILITY_ALL_WEIGHT;
    }

    if (
      ($announcement->hasField('field_announcement_unit_pages') &&
        !$announcement->get('field_announcement_unit_pages')->isEmpty()) ||
      ($announcement->hasField('field_announcement_service_pages') &&
        !$announcement->get('field_announcement_service_pages')->isEmpty())
    ) {
      return self::VISIBILITY_REGION_WEIGHT;
    }

    if (
      $announcement->hasField('field_announcement_content_pages') &&
      !$announcement->get('field_announcement_content_pages')->isEmpty()
    ) {
      return self::VISIBILITY_PAGE_WEIGHT;
    }

    return self::VISIBILITY_ALL_WEIGHT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'url.path',
      'languages:language_content',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'node_list:announcement',
      Announcements::$customCacheTag,
    ]);
  }

}
