<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;

/**
 * Base class for kernel tests.
 */
abstract class AnnifKernelTestBase extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'language',
    'helfi_api_base',
    'helfi_recommendations',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $entities = [
      'taxonomy_term',
      'suggested_topics',
    ];

    foreach ($entities as $entity) {
      $this->installEntitySchema($entity);
    }

    foreach (['fi', 'sv', 'xzz'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }

    $this->installConfig(['system', 'helfi_recommendations']);
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'name' => $this->randomMachineName(),
      'type' => 'test_node_bundle',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'test_keywords',
      'entity_type' => 'node',
      'type' => 'suggested_topics_reference',
    ])->save();

    FieldConfig::create([
      'field_name' => 'test_keywords',
      'entity_type' => 'node',
      'bundle' => 'test_node_bundle',
      'label' => 'Test field',
    ])->save();
  }

}
