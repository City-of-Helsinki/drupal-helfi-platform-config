<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Drush\Commands;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_platform_config\TextConverter\Strategy;
use Drupal\helfi_platform_config\TextConverter\TextConverterManager;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides drush commands for debugging text conversion.
 */
#[AsCommand(
  name: 'helfi:text-converter',
  description: 'Preview text conversion result.',
)]
final class TextConverterCommands extends Command {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TextConverterManager $textConverter,
    #[Autowire(service: 'logger.channel.helfi_platform_config')]
    private readonly LoggerInterface $logger,
  ) {
    parent::__construct();
  }

  /**
   * Preview entity text conversion result.
   *
   * @return int
   *   The exit code.
   */
  public function __invoke(
    InputInterface $input,
    OutputInterface $output,
    #[Argument(description: 'Entity type')]
    string $entity_type,
    #[Argument(description: 'Entity id')]
    string $id,
    #[Option(description: 'Entity language', suggestedValues: ['fi', 'sv', 'en'])]
    string $language = 'fi',
    #[Option(description: 'Text conversion strategy')]
    Strategy $strategy = Strategy::Default,
    #[Option(description: 'Split text into chunks by heading level')]
    bool $chunk = FALSE,
  ) : int {
    try {
      $entity = $this->entityTypeManager
        ->getStorage($entity_type)
        ->load($id);

      if (!$entity) {
        $output->writeln("Entity $entity_type:$id not found.");
        return self::FAILURE;
      }

      if (
        $entity instanceof TranslatableInterface &&
        $entity->hasTranslation($language)
      ) {
        $entity = $entity->getTranslation($language);
      }

      if ($chunk) {
        $chunks = $this->textConverter->chunk($entity, $strategy);

        if (empty($chunks)) {
          $output->writeln("Failed to find text converter for $entity_type:$id");
          return self::FAILURE;
        }

        foreach ($chunks as $i => $text) {
          $output->writeln("=========================================");
          $output->writeln("| Chunk $i");
          $output->writeln("=========================================");
          $output->writeln($text);
        }

        return self::SUCCESS;
      }

      if ($content = $this->textConverter->convert($entity, $strategy)) {
        $output->writeln($content);
        return self::SUCCESS;
      }

      $output->writeln("Failed to find text converter for $entity_type:$id");
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      Error::logException($this->logger, $e);
    }

    return self::FAILURE;
  }

}
