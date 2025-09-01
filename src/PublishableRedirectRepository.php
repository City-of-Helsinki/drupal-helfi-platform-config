<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Language\Language;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\RedirectRepository;

/**
 * Redirect repository takes published status into account.
 *
 * @see \Drupal\redirect\RedirectRepository
 * @see \Drupal\helfi_platform_config\HelfiPlatformConfigServiceProvider::alter
 */
class PublishableRedirectRepository extends RedirectRepository {

  /**
   * {@inheritDoc}
   */
  public function findMatchingRedirect($source_path, array $query = [], $language = Language::LANGCODE_NOT_SPECIFIED, ?CacheableMetadata $cacheable_metadata = null): ?Redirect {
    $redirect = parent::findMatchingRedirect($source_path, $query, $language);

    // If the redirect is not published, return NULL instead.
    if (
      $redirect instanceof EntityPublishedInterface
      && !$redirect->isPublished()
    ) {
      return NULL;
    }

    return $redirect;
  }

}
