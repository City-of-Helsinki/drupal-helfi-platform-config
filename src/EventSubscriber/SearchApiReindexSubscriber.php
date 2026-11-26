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
 * Reindexes a shared index after another instance created it anew.
 *
 * @see \Drupal\helfi_platform_config\SearchAPI\RestrictedElasticSearchBackend
 */
final class SearchApiReindexSubscriber implements EventSubscriberInterface {

  /**
   * The PubSub message key.
   */
  public const string MESSAGE_KEY = 'search_api_reindex';

  public function __construct(
    private readonly RestrictedSearchIndex $restrictedSearchIndex,
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.helfi_platform_config')] private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Builds the reindex message.
   *
   * @return array<string, array<string, string>>
   *   The message data.
   */
  public static function message(string $indexId, string $instance): array {
    return [self::MESSAGE_KEY => ['index' => $indexId, 'instance' => $instance]];
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
   * Marks the shared index for reindexing.
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

    // The sender already marked its own items for reindexing.
    if (!$this->restrictedSearchIndex->isShared($indexId) || $sender === $this->activeProject()) {
      return;
    }
    // This module does not depend on search_api.
    if (!$this->entityTypeManager->hasDefinition('search_api_index')) {
      return;
    }

    $index = $this->entityTypeManager->getStorage('search_api_index')->load($indexId);
    if (!$index instanceof IndexInterface || !$index->status()) {
      return;
    }

    try {
      $index->reindex();
      $this->logger->info('Shared search index @index was created again on @sender, all items were marked for reindexing.', [
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
