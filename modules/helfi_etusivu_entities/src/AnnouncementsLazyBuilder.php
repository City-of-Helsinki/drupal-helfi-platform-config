<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Utility\Error;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\helfi_api_base\Features\FeatureManagerInterface;
use Drupal\helfi_node_announcement\Entity\Announcement;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lazy builder for the announcements block.
 *
 * Renders the remote announcements first, then the local announcements that
 * are visible on the current page, most urgent and most specific first.
 */
final class AnnouncementsLazyBuilder implements TrustedCallbackInterface {

  /**
   * Announcement weights by type. More urgent types have a higher weight.
   */
  private const TYPE_WEIGHTS = [
    'notification' => 0,
    'attention' => 1,
    'alert' => 2,
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LocalNodeLoader $localNodeLoader,
    private readonly PageVisibility $pageVisibility,
    private readonly FeatureManagerInterface $featureManager,
    #[Autowire(service: 'logger.channel.helfi_etusivu_entities')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['lazyBuild'];
  }

  /**
   * Lazy builder callback.
   *
   * @param bool $useRemoteEntities
   *   Whether to include announcements from the remote source. Some instances
   *   only show their own announcements.
   *
   * @return array<mixed>
   *   The render array.
   */
  public function lazyBuild(bool $useRemoteEntities = FALSE): array {
    try {
      $local = $this->localNodeLoader->load('announcement');
      $remote = $useRemoteEntities ? $this->loadRemote() : [];
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return [];
    }

    return $this->entityTypeManager
      ->getViewBuilder('node')
      ->viewMultiple([...$remote, ...$this->visibleLocal($local)], 'default');
  }

  /**
   * Gets the visible local announcements in render order.
   *
   * @param \Drupal\node\NodeInterface[] $announcements
   *   The local announcements.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The announcements, most urgent first. Page-specific announcements come
   *   before general ones of the same type.
   */
  private function visibleLocal(array $announcements): array {
    $fields = VisibilityFields::announcement();

    $visible = array_filter(
      $announcements,
      fn (NodeInterface $announcement): bool => $this->pageVisibility->isVisible($announcement, $fields),
    );

    usort($visible, fn (NodeInterface $a, NodeInterface $b): int => [
      $this->typeWeight($b),
      $this->pageVisibility->specificity($b, $fields),
    ] <=> [
      $this->typeWeight($a),
      $this->pageVisibility->specificity($a, $fields),
    ]);

    return $visible;
  }

  /**
   * Gets the weight of the announcement type.
   *
   * @param \Drupal\node\NodeInterface $announcement
   *   The announcement.
   *
   * @return int
   *   The weight.
   */
  private function typeWeight(NodeInterface $announcement): int {
    return self::TYPE_WEIGHTS[$announcement->get('field_announcement_type')->value] ?? 0;
  }

  /**
   * Loads the remote announcements.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Unsaved announcement nodes.
   */
  private function loadRemote(): array {
    if ($this->featureManager->isEnabled(FeatureManagerInterface::USE_MOCK_RESPONSES)) {
      return $this->handleRemoteEntities($this->mockRemoteEntities());
    }

    $storage = $this->entityTypeManager->getStorage('helfi_announcements');
    if (!$storage instanceof ExternalEntityStorageInterface) {
      throw new \InvalidArgumentException('helfi_announcements is not an external entity type');
    }
    return $this->handleRemoteEntities($storage->loadMultiple());
  }

  /**
   * Converts remote announcements to unsaved announcement nodes.
   *
   * Public for testing purposes.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $remoteEntities
   *   The remote announcements.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Unsaved announcement nodes.
   */
  public function handleRemoteEntities(array $remoteEntities): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $nodes = [];
    foreach ($remoteEntities as $remote) {
      assert($remote instanceof FieldableEntityInterface);
      $announcement = $storage->create(['type' => 'announcement']);
      assert($announcement instanceof Announcement);
      $nodes[] = $announcement->populateFromRemoteEntity($remote);
    }
    return $nodes;
  }

  /**
   * Creates a remote announcement for instances that use mock responses.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The remote announcements.
   */
  private function mockRemoteEntities(): array {
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
