<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Plugin\search_api;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\helfi_platform_config\Image\LiftupImageManager;
use Drupal\helfi_platform_config_base\Token\NodeImageBuilder;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Processor\ProcessorPropertyInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Kernel\Processor\ProcessorTestBase;
use Drupal\Tests\TestFileCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the LiftupImageUrl processor.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class LiftupImageUrlProcessorTest extends ProcessorTestBase {

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_platform_config',
    'media',
    'image',
    'file',
    'config_rewrite',
    'diff',
    'helfi_api_base',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('liftup_image_url');

    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');
    $this->installConfig(['system', 'image', 'media', 'user']);

    MediaType::create([
      'id' => 'image',
      'label' => 'Image',
      'source' => 'image',
      'source_configuration' => [
        'source_field' => 'field_media_image',
      ],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'type' => 'image',
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_media_image',
      'entity_type' => 'media',
      'bundle' => 'image',
      'label' => 'Image',
    ])->save();

    NodeType::create([
      'label' => 'Article',
      'type' => 'article',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_liftup_image',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_liftup_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Liftup image',
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => ['image' => 'image'],
        ],
      ],
    ])->save();

    // The base module's install hook pulls in the whole site theme, so attach
    // its node builder here instead of enabling the module.
    $this->container->get(LiftupImageManager::class)->add(new NodeImageBuilder());

    $field = new Field($this->index, 'liftup_image_url');
    $field->setType('string');
    $field->setPropertyPath('liftup_image_url');
    $field->setLabel('Liftup image URL');
    $this->index->addField($field);
    $this->index->save();
  }

  /**
   * Tests that the property is available for every datasource.
   */
  #[Test]
  public function testPropertyDefinition(): void {
    $properties = $this->processor->getPropertyDefinitions(NULL);
    $this->assertInstanceOf(ProcessorPropertyInterface::class, $properties['liftup_image_url']);

    $datasource = $this->index->getDatasource('entity:node');
    $this->assertSame([], $this->processor->getPropertyDefinitions($datasource));
  }

  /**
   * Tests that a node liftup image is indexed as an absolute URL.
   */
  #[Test]
  public function testIndexesAbsoluteUrl(): void {
    /** @phpstan-var object{uri: string} $image */
    $image = $this->getTestFiles('image')[0];
    $file = File::create([
      'uri' => $image->uri,
    ]);
    $file->setPermanent();
    $file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Test',
      'field_media_image' => ['target_id' => $file->id()],
    ]);
    $media->save();

    $node = Node::create([
      'title' => 'With image',
      'type' => 'article',
      'field_liftup_image' => ['target_id' => $media->id()],
    ]);
    $node->save();

    $expected = $this->container->get('file_url_generator')->generateAbsoluteString($file->getFileUri());
    $this->assertSame([$expected], $this->getIndexedValues($node));
  }

  /**
   * Tests that a node without an image indexes no URL.
   */
  #[Test]
  public function testLeavesFieldEmptyWithoutImage(): void {
    $node = Node::create([
      'title' => 'Without image',
      'type' => 'article',
    ]);
    $node->save();

    $this->assertSame([], $this->getIndexedValues($node));
  }

  /**
   * Indexes a node and returns the liftup image URL values.
   *
   * @return string[]
   *   The indexed values.
   */
  private function getIndexedValues(NodeInterface $node): array {
    $id = Utility::createCombinedId('entity:node', $node->id() . ':en');
    $item = $this->container
      ->get('search_api.fields_helper')
      ->createItemFromObject($this->index, $node->getTypedData(), $id);

    return $item->getField('liftup_image_url')->getValues();
  }

}
