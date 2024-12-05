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

  /**
   * Redirect cleaner configuration.
   */
  private array $configuration;

  /**
   * Constructs a new instance.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.helfi_platform_config')]
    private readonly LoggerInterface $logger,
  ) {
    $this->configuration = $configFactory->get('helfi_platform_config.redirect_cleaner')->get();
  }

  /**
   * Return TRUE if this feature is enabled.
   */
  public function isEnabled(): bool {
    return $this->configuration['enable'] ?? FALSE;
  }

  /**
   * Unpublish expired redirects.
   */
  public function unpublishExpiredRedirects(): void {
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

    $entityType = $storage->getEntityType();

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      // Search only published redirects.
      ->condition($entityType->getKey('published'), 1)
      // That are not custom.
      ->condition($entityType->getKey('custom'), 0)
      // And expired.
      ->condition('created', strtotime('-6 months'), '<')
      // Query should have some limit.
      ->range(0, 50);

    foreach ($query->execute() as $id) {
      $redirect = $storage->load($id);
      if ($redirect instanceof PublishableRedirect) {
        $this->logger->info('Unpublishing redirect: %id', ['%id' => $redirect->id()]);

        $redirect->setUnpublished();
        $redirect->save();
      }
    }
  }

}
