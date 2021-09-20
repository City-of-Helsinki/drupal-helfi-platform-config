<?php

namespace Drupal\helfi_announcements\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Announcements' block.
 *
 * @Block(
 *   id = "announcements",
 *   admin_label = @Translation("Announcements"),
 * )
 */
class AnnouncementsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The node storage.
   *
   * @var EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language manager.
   *
   * @var LanguageManagerInterface
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
   * @param RouteMatchInterface $route_match
   *   The current route match.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
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
      $container->get('language_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    // Entity types supported by announcements and the corresponding field
    // names.
    $entityTypeFields = [
      'node' => 'field_announcement_content_pages',
      'tpr_unit' => 'field_announcement_unit_pages',
      'tpr_service' => 'field_announcement_service_pages',
    ];

    $currentEntity = $this->getCurrentPageEntity(array_keys($entityTypeFields));

    // Get all published announcement nodes.
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type','announcement')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $this->languageManager->getCurrentLanguage()->getId())
      ->execute();
    $announcementNodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $showAnnouncements = [];
    foreach ($announcementNodes as $announcementNode) {
      // Check if the announcement should be shown at all pages.
      if ($announcementNode->get('field_announcement_all_pages')->value === "1") {
        $showAnnouncements[] = $announcementNode;
        continue;
      }

      if (empty($currentEntity)) {
        continue;
      }

      // Get announcement's referenced entities from the appropriate field,
      // depending on the current page's entity.
      $referencedEntities = $announcementNode->get($entityTypeFields[$currentEntity->getEntityType()->id()])->referencedEntities();

      // Add announcement to showed announcements if current page's entity
      // is found from the list of referenced entities.
      foreach ($referencedEntities as $referencedEntity) {
        if ($referencedEntity->id() === $currentEntity->id()) {
          $showAnnouncements[] = $announcementNode;
        }
      }
    }

    if (empty($showAnnouncements)) {
      return [];
    }

    $viewMode = 'default';
    return $this->entityTypeManager->getViewBuilder('node')->viewMultiple($showAnnouncements, $viewMode);
  }

  /**
   * Get current page's entity from given possibilities.
   *
   * @param array $entityTypes
   *   Entity names to be used to check the current page.
   * @return EntityInterface|null
   *   Current page's entity, if any.
   */
  protected function getCurrentPageEntity(array $entityTypes): ?EntityInterface {
    foreach ($entityTypes as $entityType) {
      if (!empty($this->routeMatch->getParameter($entityType))) {
        return $this->routeMatch->getParameter($entityType);
      }
    }
    return NULL;
  }

}
