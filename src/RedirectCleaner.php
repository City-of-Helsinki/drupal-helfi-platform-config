<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_platform_config\Entity\PublishableRedirect;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for cleaning expired redirects.
 */
class RedirectCleaner {

  public const string ACTION_UNPUBLISH = 'unpublish';
  public const string ACTION_DELETE = 'delete';
  private const string DEFAULT_EXPIRE_AFTER = '-1 year';
  private const string DEFAULT_ACTION = self::ACTION_UNPUBLISH;

  /**
   * Constructs a new instance.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.helfi_platform_config')]
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Return TRUE if this feature is enabled.
   */
  public function isEnabled(): bool {
    return $this->configFactory
      ->get('helfi_platform_config.redirect_cleaner')
      ->get('enable') ?? FALSE;
  }

  /**
   * Clean expired redirects.
   */
  public function cleanExpiredRedirects(): void {
    if (!$this->isEnabled()) {
      return;
    }

    try {
      $storage = $this->entityTypeManager->getStorage('redirect');
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException) {
      // Redirect module most likely not installed.
      return;
    }

    $expirationTimestamp = $this->getExpirationTimestamp();
    if ($expirationTimestamp === NULL) {
      // Invalid configuration for expiration timestamp.
      return;
    }

    $action = $this->getAction();
    $entityType = $storage->getEntityType();

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      // Search only published redirects.
      ->condition($entityType->getKey('published'), 1)
      // That are not custom.
      ->condition($entityType->getKey('custom'), 0)
      // And expired.
      ->condition('created', $expirationTimestamp, '<')
      // Query should have some limit.
      ->range(0, 50);

    foreach ($query->execute() as $id) {
      $redirect = $storage->load($id);
      if (!$redirect instanceof PublishableRedirect) {
        continue;
      }

      if ($action === self::ACTION_DELETE) {
        $this->logger->info('Deleting redirect: %id', ['%id' => $redirect->id()]);
        $redirect->delete();
      }
      else {
        $this->logger->info('Unpublishing redirect: %id', ['%id' => $redirect->id()]);
        $redirect->setUnpublished();
        $redirect->save();
      }
    }
  }

  /**
   * Return the "strtotime" value for expiration time.
   *
   * The configuration value is expected to be a relative strtotime() string,
   * f.e. "-6 months" or "-1 year".
   *
   * @return int|null
   *   Return the expiration timestamp as an integer, or NULL if invalid.
   */
  private function getExpirationTimestamp(): ?int {
    $expireAfter = (string) ($this->configFactory
      ->get('helfi_platform_config.redirect_cleaner')
      ->get('expire_after') ?? self::DEFAULT_EXPIRE_AFTER);

    $timestamp = strtotime($expireAfter);
    if ($timestamp === FALSE) {
      $this->logger->warning('Invalid redirect cleaner expire_after value: %value', [
        '%value' => $expireAfter,
      ]);
      return NULL;
    }

    return $timestamp;
  }

  /**
   * Gets the cleanup action.
   *
   * @return string
   *   Return the cleanup action as a string; 'unpublish' or 'delete'.
   */
  private function getAction(): string {
    $action = (string) ($this->configFactory
      ->get('helfi_platform_config.redirect_cleaner')
      ->get('action') ?? self::DEFAULT_ACTION);

    if (!in_array($action, [self::ACTION_UNPUBLISH, self::ACTION_DELETE], TRUE)) {
      $this->logger->warning('Invalid redirect cleaner action value: %value', [
        '%value' => $action,
      ]);
      return self::DEFAULT_ACTION;
    }

    return $action;
  }

}
