<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;

/**
 * Base class for kernel tests.
 */
abstract class AnnifKernelTestBase extends EntityKernelTestBase {

  use LanguageManagerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'language',
    'helfi_api_base',
    'helfi_language_negotiator_test',
    'helfi_recommendations',
    'helfi_platform_config',
    'config_rewrite',
    'search_api',
    'elasticsearch_connector',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entities = [
      'taxonomy_term',
      'suggested_topics',
      'search_api_task',
    ];

    foreach ($entities as $entity) {
      $this->installEntitySchema($entity);
    }

    $this->setupLanguages();
    ConfigurableLanguage::createFromLangcode('xzz')->save();

    $this->installConfig(['system', 'helfi_recommendations']);
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'name' => $this->randomMachineName(),
      'type' => 'test_node_bundle',
    ])->save();

    Vocabulary::create([
      'name' => $this->randomMachineName(),
      'vid' => 'test_vocabulary',
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
