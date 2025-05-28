<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Drupal\helfi_etusivu_entities\Plugin\Block\AnnouncementsBlock;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
   * The constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\helfi_api_base\Language\DefaultLanguageResolver $defaultLanguageResolver
   *   Default language resolver.
   */
  public function __construct(
    #[Autowire(service: 'logger.channel.helfi_etusivu_entities')]
    protected LoggerInterface $logger,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected LanguageManagerInterface $languageManager,
    protected DefaultLanguageResolver $defaultLanguageResolver,
  ) {
    parent::__construct(
      $this->entityTypeManager,
      $this->routeMatch,
      $this->languageManager,
      $this->defaultLanguageResolver,
    );
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
      $remote = $this
        ->getExternalEntityStorage('helfi_announcements')
        ->loadMultiple();
      // Some non-core instances might want to show only local entities.
      // Block configuration allows disabling the remote entities.
      $remote = $useRemoteEntities && $remote ? $this->getRemoteEntities($remote) : [];
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

    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'survey');

    // Query only local nodes.
    if (isset($fields['field_publish_externally'])) {
      $query->condition('field_publish_externally', FALSE);
    }

    return $storage->loadMultiple($query->execute());
  }

  /**
   * Get remote entities.
   *
   * Public for testing purposes.
   *
   * @return array
   *   Remote announcements.
   */
  public function getRemoteEntities(array $remoteEntities): array {
    $entityStorage = $this->entityTypeManager->getStorage('node');
    $nodes = [];

    /** @var \Drupal\external_entities\ExternalEntityInterface $announcement */
    foreach ($remoteEntities as $announcement) {
      $linkUrl = NULL;
      $linkText = NULL;
      if ($announcement->hasField('announcement_link_text')) {
        $linkText = $announcement->get('announcement_link_text')->value;
        $linkUrl = $announcement->get('announcement_link_url')->value;
      }

      // Create announcement nodes for the block based on external entity data.
      $nodes[] = $entityStorage->create([
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
    $referenceField = AnnouncementsBlock::ENTITY_TYPE_FIELDS[$currentEntity?->getEntityTypeId()] ?? NULL;

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
      $announcement->hasField('field_announcement_content_pages') &&
      !$announcement->get('field_announcement_content_pages')->isEmpty()
    ) {
      return AnnouncementsBlock::VISIBILITY_PAGE_WEIGHT;
    }

    return AnnouncementsBlock::VISIBILITY_ALL_WEIGHT;
  }

}
