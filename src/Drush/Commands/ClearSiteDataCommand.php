<?php

namespace Drupal\helfi_platform_config\Drush\Commands;

use Drupal\helfi_platform_config\ClearSiteData;
use Drush\Commands\AutowireTrait;
use Drush\Attributes\Example;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: self::NAME,
  description: 'Manage the Clear-Site-Data header.',
  aliases: ['clear-site-data'],
  usages: [
    'helfi:clear-site-data status',
    'helfi:clear-site-data enable cache,storage --ttl=24',
    'helfi:clear-site-data enable "*"',
    'helfi:clear-site-data disable',
  ],
)]
final class ClearSiteDataCommand {

  use AutowireTrait;

  const NAME = 'helfi:clear-site-data';
  const OPERATIONS = ['status', 'enable', 'disable'];

  /**
   * Constructs a ClearSiteDataCommand object.
   */
  public function __construct(
    private ClearSiteData $clearSiteData,
  ) {
  }

  public function __invoke(
    InputInterface $input,
    SymfonyStyle $io,
    #[Argument(description: 'The operation to perform.')]
    string $operation = 'status',
    #[Argument(description: 'A comma separated list of directives to enable.')]
    string $directives = '',
    #[Option(description: 'The expiration time in hours.')]
    int $ttl = 1,
  ) : int {
    $operation = $input->getArgument('operation');
    if (!in_array($operation, self::OPERATIONS)) {
      $io->error(sprintf(
        'Invalid operation: %s. Possible values: %s.',
        $operation,
        implode(', ', self::OPERATIONS),
      ));
      return Command::FAILURE;
    }

    if ($operation === 'disable') {
      $this->disable($io);
    }
    elseif ($operation === 'enable') {
      if (empty($directives)) {
        $io->error(sprintf(
          'No directives provided. Possible values: %s.',
          implode(', ', ClearSiteData::VALID_DIRECTIVES),
        ));
        return Command::FAILURE;
      }

      $directives = explode(',', $directives);
      $invalid_directives = array_diff($directives, ClearSiteData::VALID_DIRECTIVES);
      if (!empty($invalid_directives)) {
        $io->error(sprintf(
          'Invalid directives: %s. Possible values: %s.',
          implode(', ', $invalid_directives),
          implode(', ', ClearSiteData::VALID_DIRECTIVES),
        ));
        return Command::FAILURE;
      }

      $ttl = $input->getOption('ttl');
      if ($ttl < ClearSiteData::MIN_EXPIRE_TIME || $ttl > ClearSiteData::MAX_EXPIRE_TIME) {
        $io->error(sprintf(
          'Invalid TTL: %s. Must be between %s and %s hours.',
          $ttl,
          ClearSiteData::MIN_EXPIRE_TIME,
          ClearSiteData::MAX_EXPIRE_TIME,
        ));
        return Command::FAILURE;
      }

      $this->enable($io, $directives, $ttl);
    }

    $this->showStatus($io);
    return Command::SUCCESS;
  }

  /**
   * Show the current "Clear-Site-Data"-header status.
   *
   * @param SymfonyStyle $io
   *   The output interface.
   */
  private function showStatus(SymfonyStyle $io) : void {
    $io->title('Current "Clear-Site-Data"-header status:');
    $values = [];

    $enable = $this->clearSiteData->getActiveEnable();
    $values[] = sprintf('Enabled: %s', $enable ? 'Yes' : 'No');

    if ($enable) {  
      $directives = $this->clearSiteData->getActiveDirectives();
      $expireAfter = $this->clearSiteData->getActiveExpireAfter();
      $values[] = sprintf('Directives: %s', $directives ? implode(', ', $directives) : 'null');
      $values[] = sprintf('Expires after: %s', $expireAfter ? date('Y-m-d H:i:s', $expireAfter) : 'null');
    }

    $active = $this->clearSiteData->isEnabled();
    if ($enable && !$active) {
      $io->warning('"Clear-Site-Data"-header is enabled, but it might be inactive due to expired TTL or empty directives.');
    }

    $io->listing($values);
  }

  /**
   * Enable the "Clear-Site-Data"-header.
   *
   * @param SymfonyStyle $io
   *   The output interface.
   */
  private function enable(SymfonyStyle $io, array $directives, int $ttl) : void {
    $this->clearSiteData->enable($directives, $ttl);
    $io->success('"Clear-Site-Data"-header enabled successfully.');
  }

  /**
   * Disable the "Clear-Site-Data"-header.
   *
   * @param SymfonyStyle $io
   *   The output interface.
   */
  private function disable(SymfonyStyle $io) : void {
    $this->clearSiteData->disable();
    $io->success('"Clear-Site-Data"-header disabled successfully.');
  }

}
