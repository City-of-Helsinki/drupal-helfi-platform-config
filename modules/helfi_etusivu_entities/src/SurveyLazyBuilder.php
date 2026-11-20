<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Utility\Error;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\helfi_node_survey\Entity\Survey;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lazy builder for the survey block.
 *
 * Renders the newest survey that is visible on the current page.
 */
final class SurveyLazyBuilder implements TrustedCallbackInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LocalNodeLoader $localNodeLoader,
    private readonly PageVisibility $pageVisibility,
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
   *   Whether to include surveys from the remote source. Some instances only
   *   show their own surveys.
   *
   * @return array<mixed>
   *   The render array.
   */
  public function lazyBuild(bool $useRemoteEntities): array {
    try {
      $surveys = [
        ...($useRemoteEntities ? $this->loadRemote() : []),
        ...$this->localNodeLoader->load('survey', 'published_at'),
      ];
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return [];
    }

    $survey = $this->selectSurvey($surveys);

    return $this->entityTypeManager
      ->getViewBuilder('node')
      ->viewMultiple($survey ? [$survey] : [], 'default');
  }

  /**
   * Selects the newest survey that is visible on the current page.
   *
   * @param \Drupal\node\NodeInterface[] $surveys
   *   The local and remote surveys.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The survey, if any.
   */
  private function selectSurvey(array $surveys): ?NodeInterface {
    usort($surveys, static fn (NodeInterface $a, NodeInterface $b): int => $b->get('published_at')->value <=> $a->get('published_at')->value);

    $fields = VisibilityFields::survey();
    foreach ($surveys as $survey) {
      if ($this->pageVisibility->isVisible($survey, $fields)) {
        return $survey;
      }
    }
    return NULL;
  }

  /**
   * Loads the remote surveys.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Unsaved survey nodes.
   */
  private function loadRemote(): array {
    $storage = $this->entityTypeManager->getStorage('helfi_surveys');
    if (!$storage instanceof ExternalEntityStorageInterface) {
      throw new \InvalidArgumentException('helfi_surveys is not an external entity type');
    }
    return $this->handleRemoteEntities($storage->loadMultiple());
  }

  /**
   * Converts remote surveys to unsaved survey nodes.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $remoteEntities
   *   The remote surveys.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Unsaved survey nodes.
   */
  private function handleRemoteEntities(array $remoteEntities): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $nodes = [];
    foreach ($remoteEntities as $remote) {
      assert($remote instanceof FieldableEntityInterface);
      $survey = $storage->create(['type' => 'survey']);
      assert($survey instanceof Survey);
      $nodes[] = $survey->populateFromRemoteEntity($remote);
    }
    return $nodes;
  }

}
