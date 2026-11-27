<?php

declare(strict_types=1);

namespace Drupal\helfi_ckeditor\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A drush command to remove empty p tags from long text fields.
 */
#[AsCommand(
  name: self::NAME,
  description: 'Remove the empty p tags from the current revisions of long text fields.',
)]
final class EmptyPTagCommand extends Command {

  use AutowireTrait;

  /**
   * The command name.
   */
  public const string NAME = 'helfi:ckeditor:remove-empty-p-tags';

  /**
   * The number of entity IDs to update per query.
   */
  private const int BATCH_SIZE = 1000;

  /**
   * The pattern of the p tags with only spaces and non-breaking spaces.
   */
  private const string EMPTY_P_TAG_PATTERN = "<p>(?:[[:space:]]|&nbsp;|\u{00A0})*</p>";

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $connection,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new DrushStyle($input, $output);

    foreach ($this->getTables() as $table) {
      $count = $this->cleanUpTable($io, $table['name'], $table['column'], $table['current']);
      $io->writeln(sprintf('%s: updated %d rows.', $table['name'], $count));
    }

    return self::SUCCESS;
  }

  /**
   * Gets the long text field tables.
   *
   * @return array<int, array{name: string, column: string, current: string|null}>
   *   Returns the table name, the value column and the current revision table.
   */
  private function getTables(): array {
    $tables = [];

    /** @var \Drupal\field\Entity\FieldConfig $fieldConfig */
    foreach ($this->entityTypeManager->getStorage('field_config')->loadMultiple() as $fieldConfig) {
      if ($fieldConfig->getType() !== 'text_long') {
        continue;
      }

      $definition = $fieldConfig->getFieldStorageDefinition();

      /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($fieldConfig->getTargetEntityTypeId());
      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $tableMapping */
      $tableMapping = $storage->getTableMapping();
      $column = $tableMapping->getFieldColumnName($definition, 'value');
      $dataTable = $tableMapping->getDedicatedDataTableName($definition);

      $tables[$dataTable] = [
        'name' => $dataTable,
        'column' => $column,
        'current' => NULL,
      ];

      if (!$storage->getEntityType()->isRevisionable()) {
        continue;
      }

      // Limit the revision table to the current revisions.
      $revisionTable = $tableMapping->getDedicatedRevisionTableName($definition);
      $tables[$revisionTable] = [
        'name' => $revisionTable,
        'column' => $column,
        'current' => $dataTable,
      ];
    }

    return array_values($tables);
  }

  /**
   * Removes the empty p tags from a table in entity ID batches.
   *
   * @param \Drush\Style\DrushStyle $io
   *   The console style.
   * @param string $table
   *   The table name.
   * @param string $column
   *   The value column name.
   * @param string|null $currentTable
   *   The data table with the current revision IDs.
   *
   * @return int
   *   Returns the number of updated rows.
   */
  private function cleanUpTable(DrushStyle $io, string $table, string $column, ?string $currentTable): int {
    $query = $this->connection->select($table, 't');
    $query->addExpression('MIN(entity_id)', 'min_id');
    $query->addExpression('MAX(entity_id)', 'max_id');
    $range = $query->execute()->fetchAssoc();

    if (!$range || $range['min_id'] === NULL) {
      return 0;
    }

    $min = (int) $range['min_id'];
    $max = (int) $range['max_id'];
    $count = 0;

    $io->progressStart((int) ceil(($max - $min + 1) / self::BATCH_SIZE));

    for ($start = $min; $start <= $max; $start += self::BATCH_SIZE) {
      $ids = [$start, $start + self::BATCH_SIZE - 1];

      $update = $this->connection->update($table)
        ->expression($column, "REGEXP_REPLACE($column, :pattern, '')", [':pattern' => self::EMPTY_P_TAG_PATTERN])
        ->condition('entity_id', $ids, 'BETWEEN')
        ->condition($column, self::EMPTY_P_TAG_PATTERN, 'REGEXP');

      if ($currentTable) {
        $currentRevisions = $this->connection->select($currentTable, 'c')
          ->fields('c', ['revision_id'])
          ->condition('entity_id', $ids, 'BETWEEN');
        $update->condition('revision_id', $currentRevisions, 'IN');
      }

      $count += $update->execute();

      $io->progressAdvance();
    }

    $io->progressFinish();

    return $count;
  }

}
