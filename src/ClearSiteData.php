<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * The Clear-Site-Data header service.
 */
class ClearSiteData {

  const CONFIG_NAME = 'helfi_platform_config.clear_site_data';

  // Valid directives for the Clear-Site-Data header.
  // @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Clear-Site-Data#directives
  const VALID_DIRECTIVES = [
    'cache',
    'clientHints',
    'cookies',
    'executionContexts',
    'prefetchCache',
    'prerenderCache',
    'storage',
    '*',
  ];

  // Minimum and maximum expire time in hours.
  const MIN_EXPIRE_TIME = 1;
  const MAX_EXPIRE_TIME = 24;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Checks if the Clear-Site-Data header is enabled.
   *
   * @return bool
   *   TRUE if the Clear-Site-Data header is enabled, FALSE otherwise.
   */
  public function isEnabled() : bool {
    $enable = $this->getActiveEnable();
    $directives = $this->getActiveDirectives();
    $expire_after = $this->getActiveExpireAfter();
    $request_time = $this->time->getRequestTime();

    if (!$enable || empty($directives) || !$expire_after || $expire_after < $request_time) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Enables the Clear-Site-Data header.
   *
   * @param array $directives
   *   The directives to clear.
   * @param int $expire_time
   *   The expire time in hours. Defaults to 1 hour.
   *
   * @throws \InvalidArgumentException
   *   If the directives are invalid or the expire time is out of range.
   */
  public function enable(array $directives, int $expire_time = 1) : void {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);

    $directives_valid = array_filter($directives, fn($directive) => in_array($directive, self::VALID_DIRECTIVES));
    if (empty($directives_valid)) {
      throw new \InvalidArgumentException(sprintf(
        'Invalid Clear-Site-Data directives. Valid directives are: %s',
        implode(', ', self::VALID_DIRECTIVES),
      ));
    }

    if ($expire_time < self::MIN_EXPIRE_TIME || $expire_time > self::MAX_EXPIRE_TIME) {
      throw new \InvalidArgumentException(sprintf(
        'Invalid Clear-Site-Data expire time. Must be between %d and %d hours.',
        self::MIN_EXPIRE_TIME,
        self::MAX_EXPIRE_TIME,
      ));
    }

    // If the * directive is present, no need for other directives.
    if (in_array('*', $directives_valid)) {
      $directives_valid = ['*'];
    }

    $config->set('enable', TRUE);
    $config->set('directives', $directives_valid);
    $config->set('expire_after', $this->time->getRequestTime() + $expire_time * 60 * 60);
    $config->save();
  }

  /**
   * Disables the Clear-Site-Data header.
   */
  public function disable() : void {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);
    $config->set('enable', FALSE);
    $config->set('directives', NULL);
    $config->set('expire_after', NULL);
    $config->save();
  }

  /**
   * Get active enable status.
   *
   * @return bool
   *   The active enable status.
   */
  public function getActiveEnable() : bool {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    return $config->get('enable');
  }

  /**
   * Get active directives.
   *
   * @return string[]|null
   *   The active directives.
   */
  public function getActiveDirectives() : array|null {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    return $config->get('directives');
  }

  /**
   * Get active expire after timestamp.
   *
   * @return int|null
   *   The active expire after timestamp.
   */
  public function getActiveExpireAfter() : int|null {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    return $config->get('expire_after');
  }

  /**
   * Disables the Clear-Site-Data header if it has expired.
   */
  public function disableIfExpired() : void {
    $expire_after = $this->getActiveExpireAfter();
    if ($expire_after && $expire_after < $this->time->getRequestTime()) {
      $this->disable();
    }
  }

  /**
   * Get dependency metadata object for the Clear-Site-Data header.
   *
   * @return \Drupal\Core\Cache\CacheableDependencyInterface
   *   The dependency metadata object.
   */
  public function getDependencyMetadata() : CacheableDependencyInterface {
    return $this->configFactory->get(self::CONFIG_NAME);
  }

}
