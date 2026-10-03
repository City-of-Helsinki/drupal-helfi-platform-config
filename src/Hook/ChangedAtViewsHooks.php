<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_platform_config\DTO\ChangedAtFieldBundle;

/**
 * Exposes the reusable 'changed_at' field to Views.
 *
 * The 'changed_at' field is a bundle field stored in a dedicated table, so
 * Views are not able to use it automatically. This provides Views integration.
 *
 * @see \Drupal\helfi_platform_config\Hook\ChangedAtFieldHooks
 * @see \Drupal\views\EntityViewsData::processViewsDataForTimestamp()
 */
class ChangedAtViewsHooks {

  use AutowireTrait;
  use StringTranslationTrait;

  private const string FIELD_NAME = 'changed_at';

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * Gets the opted-in bundles, keyed by entity type id.
   *
   * @return array<string, string[]>
   *   The bundles, keyed by entity type id.
   */
  private function getConfiguredBundles(): array {
    $bundles = [];

    foreach ($this->moduleHandler->invokeAll('helfi_changed_at_field_bundles') as $bundle) {
      if ($bundle instanceof ChangedAtFieldBundle) {
        $bundles[$bundle->entityType][] = $bundle->bundle;
      }
    }
    return $bundles;
  }

  /**
   * Implements hook_views_data().
   *
   * @return array<string, mixed>
   *   The views data.
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data = [];

    foreach ($this->getConfiguredBundles() as $entityTypeId => $bundles) {
      $data += $this->buildViewsData($entityTypeId, array_unique($bundles));
    }
    return $data;
  }

  /**
   * Builds the views data for the given entity type.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string[] $bundles
   *   The bundles that have the field.
   *
   * @return array<string, mixed>
   *   The views data.
   */
  private function buildViewsData(string $entityTypeId, array $bundles): array {
    if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
      return [];
    }
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $fieldStorage = $this->entityFieldManager->getFieldStorageDefinitions($entityTypeId)[self::FIELD_NAME] ?? NULL;

    if (!$storage instanceof SqlContentEntityStorage || !$fieldStorage) {
      return [];
    }
    $tableMapping = $storage->getTableMapping();

    if (!$tableMapping instanceof DefaultTableMapping || !$tableMapping->requiresDedicatedTableStorage($fieldStorage)) {
      return [];
    }
    $column = $tableMapping->getFieldColumnName($fieldStorage, 'value');
    $translatable = $fieldStorage->isTranslatable();
    $group = $entityType->getLabel();

    // Join via the data tables when available, so each translation gets its
    // own 'changed_at' value.
    $tables = [
      [
        'table' => $tableMapping->getDedicatedDataTableName($fieldStorage),
        'left_table' => (string) ($entityType->getDataTable() ?: $entityType->getBaseTable()),
        'left_field' => (string) $entityType->getKey('id'),
        'field' => 'entity_id',
        'join_langcode' => $translatable && $entityType->getDataTable(),
        'group' => $group,
        'field_alias' => self::FIELD_NAME,
        'is_revision' => FALSE,
      ],
    ];

    if ($entityType->isRevisionable() && $entityType->getRevisionTable()) {
      $tables[] = [
        'table' => $tableMapping->getDedicatedRevisionTableName($fieldStorage),
        'left_table' => $entityType->getRevisionDataTable() ?: $entityType->getRevisionTable(),
        'left_field' => (string) $entityType->getKey('revision'),
        'field' => 'revision_id',
        'join_langcode' => $translatable && $entityType->getRevisionDataTable(),
        'group' => $this->t('@group (historical data)', ['@group' => $group]),
        // Matches the alias core uses for revision tables of configurable
        // fields.
        'field_alias' => self::FIELD_NAME . '__revision_id',
        'is_revision' => TRUE,
      ];
    }

    $data = [];

    foreach ($tables as $info) {
      $data[$info['table']] = $this->buildTableData($entityTypeId, $bundles, $column, $info);
    }
    return $data;
  }

  /**
   * Builds the views data for a single 'changed_at' table.
   *
   * @param string $entityTypeId
   *   The entity type id.
   * @param string[] $bundles
   *   The bundles that have the field.
   * @param string $column
   *   The value column name.
   * @param array{table: string, left_table: string, left_field: string, field: string, join_langcode: bool, group: \Stringable|string, field_alias: string, is_revision: bool} $info
   *   The table information.
   *
   * @return array<string, mixed>
   *   The views data for the table.
   */
  private function buildTableData(string $entityTypeId, array $bundles, string $column, array $info): array {
    $join = [
      'left_field' => $info['left_field'],
      'field' => $info['field'],
      'extra' => [
        ['field' => 'deleted', 'value' => 0, 'numeric' => TRUE],
      ],
    ];

    if ($info['join_langcode']) {
      $join['extra'][] = ['left_field' => 'langcode', 'field' => 'langcode'];
    }

    $label = $this->t('Content changed on');
    $help = $this->t('Timestamp of when content was last changed via form. Appears in: @bundles.', [
      '@bundles' => implode(', ', $bundles),
    ]);
    $handler = [
      'field_name' => self::FIELD_NAME,
      'entity_type' => $entityTypeId,
    ];

    return [
      'table' => [
        'group' => $info['group'],
        'provider' => 'helfi_platform_config',
        'join' => [
          $info['left_table'] => $join,
        ],
      ],
      $info['field_alias'] => [
        'group' => $info['group'],
        'title' => $label,
        'help' => $help,
        'field' => [
          'id' => 'field',
          'table' => $info['table'],
          'real field' => $column,
          'additional fields' => ['delta', 'langcode', 'bundle', $column],
          'element type' => 'div',
          'is revision' => $info['is_revision'],
        ] + $handler,
      ],
      $column => [
        'group' => $info['group'],
        'title' => $this->t('@label (@name)', ['@label' => $label, '@name' => self::FIELD_NAME]),
        'title short' => $label,
        'help' => $help,
        'argument' => ['id' => 'date'] + $handler,
        'filter' => ['id' => 'date'] + $handler,
        'sort' => ['id' => 'date'] + $handler,
      ],
    ];
  }

}
