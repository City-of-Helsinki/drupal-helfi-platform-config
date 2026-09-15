<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Drush\Commands;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\helfi_search\Queue\QueueManager;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Puts one document back to the embedding work list.
 */
#[AsCommand(
  name: 'helfi:search:embed',
  description: 'Mark an entity translation as pending embedding.',
)]
final class EmbedCommands extends Command {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueManager $queueManager,
  ) {
    parent::__construct();
  }

  /**
   * Marks the entity translations as pending.
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
    #[Option(description: 'Langcode', suggestedValues: ['fi', 'sv', 'en'])]
    ?string $language = NULL,
  ): int {
    $entity = $this->entityTypeManager
      ->getStorage($entity_type)
      ->load($id);

    if (!$entity instanceof ContentEntityInterface) {
      $output->writeln("<error>Entity $entity_type:$id not found.</error>");
      return self::FAILURE;
    }

    if ($language !== NULL) {
      if (!$entity->hasTranslation($language)) {
        $output->writeln("<error>Entity $entity_type:$id has no '$language' translation.</error>");
        return self::FAILURE;
      }
      $languages = [$language];
    }
    else {
      $languages = array_keys($entity->getTranslationLanguages());
    }

    $marked = 0;

    foreach ($languages as $langcode) {
      $translation = $entity->getTranslation($langcode);
      assert($translation instanceof ContentEntityInterface);

      $this->queueManager->markForProcessing($translation);
      $marked++;
    }

    $output->writeln("<info>Marked $marked document(s) for embedding.</info>");

    return self::SUCCESS;
  }

}
