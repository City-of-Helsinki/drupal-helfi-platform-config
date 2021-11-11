<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Commands;

use Drupal\Core\Database\Connection;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 */
final class ChangeCollationCommands extends DrushCommands {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Changes the collation of two database fields for view sorting.
   *
   * @command helfi:change-collation
   */
  public function change() : void {
    $this->connection->query('ALTER TABLE {tpr_unit_field_data} MODIFY COLUMN name varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;');
    $this->connection->query('ALTER TABLE {tpr_unit_field_data} MODIFY COLUMN name_override varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;');
  }

}
