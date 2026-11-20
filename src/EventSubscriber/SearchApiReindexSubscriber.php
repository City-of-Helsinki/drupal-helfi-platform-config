<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_api_base\Azure\PubSub\PubSubMessage;
use Drupal\helfi_api_base\Environment\EnvironmentResolverException;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_platform_config\SearchAPI\RestrictedSearchIndex;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resets the tracker of a shared index when another instance cleared it.
 *
 * The message is sent by SearchApiClearListener after a successful
 * drush search-api:clear. Messages are received by the
 * helfi:azure:pubsub-listen Drush command.
 */
final class SearchApiReindexSubscriber implements EventSubscriberInterface {

  /**
   * The PubSub message key for a shared index reindex request.
   */
  public const string MESSAGE_KEY = 'search_api_reindex';

  public function __construct(
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.helfi_platform_config')] private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PubSubMessage::class => ['onReceive'],
    ];
  }

  /**
   * Marks every item of the shared index for reindexing.
   */
  public function onReceive(PubSubMessage $message): void {
    $payload = $message->data['data'][self::MESSAGE_KEY] ?? NULL;
    if (!is_array($payload)) {
      return;
    }
    $indexId = $payload['index'] ?? NULL;
    $sender = $payload['instance'] ?? NULL;
    if (!is_string($indexId) || $indexId === '' || !is_string($sender) || $sender === '') {
      return;
    }

    // The sending instance already reindexes as part of the clear.
    if ($sender === $this->activeProject()) {
      return;
    }
    // helfi_platform_config does not depend on search_api, but every
    // instance receives the message.
    if (!RestrictedSearchIndex::isShared($indexId) || !$this->entityTypeManager->hasDefinition('search_api_index')) {
      return;
    }

    $index = $this->entityTypeManager->getStorage('search_api_index')->load($indexId);
    if (!$index instanceof IndexInterface || !$index->status()) {
      return;
    }

    try {
      $index->reindex();
      $this->logger->info('Search index @index was cleared on @sender, all items were marked for reindexing.', [
        '@index' => $indexId,
        '@sender' => $sender,
      ]);
    }
    catch (SearchApiException $e) {
      Error::logException($this->logger, $e);
    }
  }

  /**
   * The active project name, or NULL when it cannot be resolved.
   */
  private function activeProject(): ?string {
    try {
      return $this->environmentResolver->getActiveProject()->getName();
    }
    catch (EnvironmentResolverException) {
      return NULL;
    }
  }

}
