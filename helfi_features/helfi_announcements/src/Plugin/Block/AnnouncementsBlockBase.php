<?php

namespace Drupal\helfi_announcements\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AnnouncementsBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  public const VISIBILITY_ALL_WEIGHT = 0;
  public const VISIBILITY_REGION_WEIGHT = 1;
  public const VISIBILITY_PAGE_WEIGHT = 2;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a new AnnouncementsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteMatchInterface $route_match,
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * Get current page's entity from given possibilities.
   *
   * @param array $entityTypes
   *   Entity names to be used to check the current page.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Current page's entity, if any.
   */
  protected function getCurrentPageEntity(array $entityTypes): ?EntityInterface {
    foreach ($entityTypes as $entityType) {
      $pageEntity = $this->routeMatch->getParameter($entityType);
      if (!empty($pageEntity) && $pageEntity instanceof EntityInterface) {
        return $pageEntity;
      }
    }
    return NULL;
  }

  /**
   * Sort announcements by type/severity and by visibility.
   *
   * @param \Drupal\node\NodeInterface[] $announcements
   *   Array of nodes.
   */
  protected function sortAnnouncements(array &$announcements): void {
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
   * Execute sorting.
   *
   * @param \Drupal\node\NodeInterface[] $announcements
   *   Announcement entities.
   * @param array $announcementTypeWeights
   *   Announcement types ordered by severity.
   */
  protected function doSort(array &$announcements, array $announcementTypeWeights): void {
    // Sort by type/severity.
    usort($announcements, function (
      EntityInterface $a,
      EntityInterface $b
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
   * Create the map which is used to order the announcements by severity.
   *
   * @param array $announcementTypes
   *   Should return ['notification' => 0, 'attention' => 1, 'alert' => 2].
   *
   * @return int[]|string[]
   *   Array of announcement type keys and weights.
   */
  protected function createAnnouncementWeightMap(array $announcementTypes): array {
    return array_flip(array_keys($announcementTypes));
  }

  /**
   * Return weight for announcement visibility.
   *
   * @param Drupal\Core\Entity\EntityInterface $announcement
   *   Announcement entity.
   *
   * @return int
   *   Visibility weight.
   */
  protected function resolveVisibilityWeight(EntityInterface $announcement): int {
    if ($announcement->get('field_announcement_all_pages')->value == TRUE) {
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
      ($announcement->hasField('field_announcement_content_pages') &&
        !$announcement->get('field_announcement_content_pages')->isEmpty())
    ) {
      return self::VISIBILITY_PAGE_WEIGHT;
    }

    return self::VISIBILITY_ALL_WEIGHT;
  }

}
