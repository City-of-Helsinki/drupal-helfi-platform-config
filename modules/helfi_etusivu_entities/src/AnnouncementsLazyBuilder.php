<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_api_base\Features\FeatureManagerInterface;
use Drupal\helfi_etusivu_entities\Plugin\Block\AnnouncementsBlock;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The announcement lazy builder.
 */
final class AnnouncementsLazyBuilder extends LazyBuilderBase {

  /**
   * Announcement weights by type.
   *
   * @var int[]
   */
  public static $announcementTypeWeights = ['notification' => 0, 'attention' => 1, 'alert' => 2];

  /**
   * The feature manager.
   */
  private FeatureManagerInterface $featureManager;

  /**
   * Injects dependencies not available via the base class.
   *
   * @param \Drupal\helfi_api_base\Features\FeatureManagerInterface $featureManager
   *   The feature manager.
   */
  #[Required]
  public function setFeatureManager(FeatureManagerInterface $featureManager): void {
    $this->featureManager = $featureManager;
  }

  /**
   * Lazy building for announcements.
   *
   * @return array
   *   The render array for announcements.
   */
  public function lazyBuild(bool $useRemoteEntities = FALSE): array {
    try {
      $local = $this->getLocalEntities();

      $remote = [];

      // Some non-core instances might want to show only local entities.
      // Block configuration allows disabling the remote entities.
      if ($useRemoteEntities) {
        if ($this->featureManager->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)) {
          $remote = $this->useMockResponse();
        }
        else {
          $remote = $this
            ->getExternalEntityStorage('helfi_announcements')
            ->loadMultiple();
        }

        $remote = $this->handleRemoteEntities($remote);
      }
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return [];
    }

    $sorted = $this->sortEntities($local, $remote);

    return $this->entityTypeManager
      ->getViewBuilder('node')
      ->viewMultiple($sorted, 'default');
  }

  /**
   * Get local announcements.
   *
   * @return array
   *   Local announcement entities.
   */
  private function getLocalEntities(): array {
    $langcodes = $this->getContentLangcodes();
    $storage = $this->entityTypeManager->getStorage('node');

    // Get all published announcement nodes.
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'announcement')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $langcodes, 'IN');

    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'announcement');

    // Query only local nodes.
    if (isset($fields['field_publish_externally'])) {
      $query->condition('field_publish_externally', FALSE);
    }

    return $storage->loadMultiple($query->execute());
  }

  /**
   * Handle the remote entities.
   *
   * Public for testing purposes.
   *
   * @param array $remoteEntities
   *   Remote entities.
   *
   * @return array
   *   Remote announcements.
   */
  public function handleRemoteEntities(array $remoteEntities): array {
    $nodes = [];

    /** @var \Drupal\external_entities\Entity\ExternalEntityInterface $announcement */
    foreach ($remoteEntities as $announcement) {
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
        // Run body through 'minimal' text filter.
        'body' => ['value' => $announcement->get('body')->value, 'format' => 'minimal'],
        'title' => $announcement->get('title')->value,
        'status' => $announcement->get('status')->value,
        'field_announcement_title' => $announcement->get('announcement_assistive_technology_close_button_title')->value,
        'field_announcement_type' => $announcement->get('announcement_type')->value,
        'field_announcement_link' => ['uri' => $linkUrl, 'title' => $linkText],
      ]);
    }

    return $nodes;
  }

  /**
   * Sort the entities.
   *
   * First local/remote, then by type & weight.
   *
   * @param array $local
   *   Local entities.
   * @param array $remote
   *   Remote entities.
   *
   * @return array
   *   All entities sorted.
   *
   * @codeCoverageIgnore
   */
  private function sortEntities(array $local, array $remote): array {
    $currentEntity = $this->getCurrentPageEntity(array_keys(AnnouncementsBlock::ENTITY_TYPE_FIELDS));

    $entityTypeId = $currentEntity?->getEntityTypeId();
    $referenceField = $entityTypeId ? AnnouncementsBlock::ENTITY_TYPE_FIELDS[$entityTypeId] : NULL;

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

      $matchesReference = !empty($referenceField)
        && $this->hasReference($referenceField, $announcementNode, $currentEntity);
      $matchesPath = $this->matchesPagePaths($announcementNode, 'field_announcement_page_paths');
      if ($matchesReference || $matchesPath) {
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
   *
   * @codeCoverageIgnore
   */
  private function sortAnnouncements(array &$announcements): void {
    // Sort by type/severity.
    usort($announcements, function (
      EntityInterface $a,
      EntityInterface $b,
    ) {
      $announcementTypeWeights = self::$announcementTypeWeights;
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
   *
   * @codeCoverageIgnore
   */
  private function resolveVisibilityWeight(ContentEntityInterface $announcement): int {
    if ($announcement->get('field_announcement_all_pages')->value) {
      return AnnouncementsBlock::VISIBILITY_ALL_WEIGHT;
    }

    if (
      ($announcement->hasField('field_announcement_unit_pages') &&
        !$announcement->get('field_announcement_unit_pages')->isEmpty()) ||
      ($announcement->hasField('field_announcement_service_pages') &&
        !$announcement->get('field_announcement_service_pages')->isEmpty())
    ) {
      return AnnouncementsBlock::VISIBILITY_REGION_WEIGHT;
    }

    if (
      ($announcement->hasField('field_announcement_content_pages') &&
        !$announcement->get('field_announcement_content_pages')->isEmpty()) ||
      ($announcement->hasField('field_announcement_page_paths') &&
        !$announcement->get('field_announcement_page_paths')->isEmpty())
    ) {
      return AnnouncementsBlock::VISIBILITY_PAGE_WEIGHT;
    }

    return AnnouncementsBlock::VISIBILITY_ALL_WEIGHT;
  }

  /**
   * Use mock response.
   *
   * @return array
   *   Array of entities.
   */
  private function useMockResponse(): array {
    $entity = $this->entityTypeManager
      ->getStorage('helfi_announcements')->create([
        'uuid' => 'c9ee55c3-9ca5-4c53-900e-82b6d6928a99',
        'langcode' => 'en',
        'body' => 'body',
        'title' => 'title3',
        'status' => NodeInterface::PUBLISHED,
        'field_announcement_title' => 'The title3',
        'field_announcement_type' => 'alert',
        'field_announcement_all_pages' => 0,
        'field_announcement_assistive_technology_close_button_title' => 'assistance',
      ]);
    return [$entity];
  }

}
