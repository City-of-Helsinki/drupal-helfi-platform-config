<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 */
final class ParagraphCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private Connection $connection,
  ) {
  }

  /**
   * Lists paragraphs without any parent.
   *
   * @field-labels
   *   id: Paragraph Id
   *   parent_field_name: Field
   *   parent_type: Type
   *   parent_id: Parent id
   *   langcode: Langcode
   * @default-fields id,parent_field_name,parent_type,parent_id,langcode
   * @option fix Fix back reference automatically.
   *
   * @command helfi:scan-paragraph-fields
   */
  public function scan(
    $options = [
      'format' => 'table',
      'fix' => FALSE,
      'ids' => '',
    ],
  ) : RowsOfFields {
    $values = $this->connection->select('paragraphs_item_field_data', 'p')
      ->fields('p')
      ->execute();

    $items = [];
    $ids = array_filter(explode(',', $options['ids']));

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
        if (!empty($ids) && !in_array($value->id, $ids)) {
          continue;
        }
        $items[] = $value;

        if ($options['fix']) {
          $paragraph = Paragraph::load($value->id);

          if (!$paragraph || !$paragraph->hasTranslation($value->langcode)) {
            $this->output()->writeln('Translation not found for given paragraph.');
          }
          $paragraph = $paragraph->getTranslation($value->langcode);
          $entity->get($value->parent_field_name)->appendItem($paragraph);
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
