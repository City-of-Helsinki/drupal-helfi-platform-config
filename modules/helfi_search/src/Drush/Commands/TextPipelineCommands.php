<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Drush\Commands;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Utility\Error;
use Drupal\helfi_search\Pipeline\TextPipeline;
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
 * Provides drush commands for debugging text pipeline.
 */
#[AsCommand(
  name: 'helfi:text-pipeline',
  description: 'Preview text conversion result.',
)]
final class TextPipelineCommands extends Command {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TextPipeline $textPipeline,
    #[Autowire(service: 'logger.channel.helfi_search')]
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

      $results = $this->textPipeline->processEntities([$entity]);

      if (empty($results)) {
        $output->writeln("Failed to find text converter for $entity_type:$id");
        return self::FAILURE;
      }

      foreach ($results as $chunks) {
        foreach ($chunks as $chunk) {
          $output->writeln($chunk['content']);
          $output->writeln("=========================================");
        }
      }

      return self::SUCCESS;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      Error::logException($this->logger, $e);
    }

    return self::FAILURE;
  }

}
