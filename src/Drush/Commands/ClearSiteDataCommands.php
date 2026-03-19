<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Drush\Commands;

use Drush\Attributes\Command;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drupal\helfi_platform_config\ClearSiteData;

/**
 * A drush command file.
 */
final class ClearSiteDataCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private ClearSiteData $clearSiteData,
  ) {
  }

  /**
   * Enables the Clear-Site-Data header.
   *
   * Provides interactive selection of directives and expiration time.
   */
  #[Command(name: 'helfi:clear-site-data:enable')]
  public function enable() : int {
    $active_directives = $this->clearSiteData->getActiveDirectives();
    $active_expire_after = $this->clearSiteData->getActiveExpireAfter();

    // Select directives.
    $directives = $this->io()->choice(
      question: 'Select directives',
      choices: ClearSiteData::VALID_DIRECTIVES,
      default: $active_directives ?? NULL,
      multiSelect: TRUE,
      required: TRUE,
      hint: $active_directives ? sprintf(
        'Currently active directives: %s',
        implode(',', $active_directives),
      ) : '',
    );

    // Ask expiration time in hours.
    $ttl = $this->io()->ask(
      question: 'Give expiration time in hours',
      default: '1',
      required: TRUE,
      validate: function ($value) {
        if (!is_numeric($value) || $value <= 0 || $value > 24) {
          return sprintf(
            'Expiration time must be between %s and %s hours.',
            ClearSiteData::MIN_EXPIRE_TIME,
            ClearSiteData::MAX_EXPIRE_TIME,
          );
        }
      },
      hint: $active_expire_after ? sprintf(
        'Currently active expiration time: %s',
        date('Y-m-d H:i:s', $active_expire_after),
      ) : '',
    );

    $this->clearSiteData->enable($directives, (int) $ttl);

    $this->io()->success(sprintf(
      '"Clear-Site-Data"-header enabled with directives: %s and expiration time: %s hours (%s).',
      implode(',', $this->clearSiteData->getActiveDirectives()),
      $ttl,
      date('Y-m-d H:i:s', $this->clearSiteData->getActiveExpireAfter()),
    ));

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Disables the Clear-Site-Data header.
   */
  #[Command(name: 'helfi:clear-site-data:disable')]
  public function disable() : int {
    $this->clearSiteData->disable();

    $this->io()->success('"Clear-Site-Data"-header disabled.');
    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Shows the status of the Clear-Site-Data header.
   */
  #[Command(name: 'helfi:clear-site-data:status')]
  public function status() : int {
    $enable = $this->clearSiteData->isEnabled();
    if ($enable) {
      $status = sprintf(
        '"Clear-Site-Data"-header is enabled with directives: %s and expiration time %s.',
        implode(',', $this->clearSiteData->getActiveDirectives()),
        date('Y-m-d H:i:s', $this->clearSiteData->getActiveExpireAfter()),
      );
    }
    else {
      $status = '"Clear-Site-Data"-header is disabled.';
    }

    $this->io()->info($status);
    return DrushCommands::EXIT_SUCCESS;
  }

}
