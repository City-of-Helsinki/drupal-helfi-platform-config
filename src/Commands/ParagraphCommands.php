<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 */
final class ParagraphCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection) {
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
  }

  /**
   * Lists paragraphs without any parent.
   *
   * @field-labels
   *   parent_field_name: Field
   *   parent_type: Type
   *   parent_id: Id
   *   langcode: Langcode
   * @default-fields parent_field_name,parent_type,parent_id,langcode
   * @option fix Fix back reference automatically.
   *
   * @command helfi:scan-paragraph-fields
   */
  public function scan($options = ['format' => 'table', 'fix' => FALSE]) : RowsOfFields {
    $values = $this->connection->select('paragraphs_item_field_data', 'p')
      ->fields('p')
      ->execute();

    $items = [];

    foreach ($values as $value) {
      $entity = $this->entityTypeManager
        ->getStorage($value->parent_type)
        ->load($value->parent_id);

      // Skip non-translatable entities.
      if (!$entity instanceof ContentEntityInterface|| !$entity->hasTranslation($value->langcode)) {
        continue;
      }
      $entity = $entity->getTranslation($value->langcode);

      if ($entity->get($value->parent_field_name)->isEmpty()) {
        $items[] = $value;

        if ($options['fix'] && $paragraph = Paragraph::load($value->id)) {
          $entity->get($value->parent_field_value)->appendItem($paragraph);
          $entity->save();
        }
      }
    }

    $rows = array_map(function ($data) use ($options) {
      $row = [];
      foreach (explode(',', $options['fields']) as $field) {
        if (!isset($data->{$field})) {
          continue;
        }
        $row[$field] = $data->{$field};
      }
      return $row;
    }, $items);

    return new RowsOfFields($rows);
  }

}
