<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config_base\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\helfi_paragraphs_hero\Entity\Hero;
use Drupal\helfi_platform_config_base\Token\NodeImageBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Tests the NodeImageBuilder.
 *
 * @coversDefaultClass \Drupal\helfi_platform_config_base\Token\NodeImageBuilder
 *
 * @group helfi_platform_config_base
 */
final class NodeImageBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'entity_reference_revisions',
    'field',
    'file',
    'helfi_paragraphs_hero',
    'helfi_platform_config_base',
    'image',
    'media',
    'node',
    'options',
    'paragraphs',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * The node image builder.
   *
   * @var \Drupal\helfi_platform_config_base\Token\NodeImageBuilder
   */
  private NodeImageBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install schemas and entity schemas.
    $this->installSchema('system', ['sequences']);
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('paragraph');

    // Install core module configurations.
    $this->installConfig([
      'node',
      'media',
      'taxonomy',
      'paragraphs',
    ]);

    // Create the necessary entity types for testing.
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    Vocabulary::create(['vid' => 'organization', 'name' => 'Organization'])->save();
    ParagraphsType::create(['id' => 'hero', 'label' => 'Hero'])->save();

    // Create necessary fields for testing.
    $this->addMediaReferenceField('node', 'page', 'field_liftup_image');
    $this->addMediaReferenceField('node', 'page', 'field_image');
    $this->addHeroReferenceField('node', 'page', 'field_hero');
    $this->addOrganizationReferenceField('node', 'page', 'field_organization');
    $this->addMediaReferenceField('paragraph', 'hero', 'field_hero_image');
    $this->addHeroDesignField();
    $this->addFileReferenceField('taxonomy_term', 'organization', 'field_default_image');
    $this->createImageMediaType();

    // Create the node image builder.
    $this->builder = new NodeImageBuilder();
  }

  /**
   * Test that NodeImageBuilder applies to only node entities.
   *
   * @covers ::applies
   */
  public function testApplies(): void {
    $node = $this->createNode();
    $term = Term::create(['vid' => 'organization', 'name' => 'Organization']);
    $term->save();

    // Assert that the NodeImageBuilder applies to only node entities.
    $this->assertTrue($this->builder->applies($node));
    $this->assertFalse($this->builder->applies($term));
    $this->assertFalse($this->builder->applies(NULL));
  }

  /**
   * Test that NodeImageBuilder returns NULL when no image is available.
   *
   * @covers ::buildUri
   */
  public function testBuildUriReturnsNullWhenNoImage(): void {
    $node = $this->createNode();
    $this->assertNull($this->builder->buildUri($node));
  }

  /**
   * Test the buildUri logic.
   *
   * The logic is as follows:
   * - If liftup image is set, use it
   * - If liftup image is not set, use node image
   *   - job_listing in rekry
   * - If node image is not set, use hero image
   *   - Hero paragraphs in landing page or basic page
   * - If hero image is not set, use organization default
   *   - Organization default image in taxonomy term, in rekry
   * - If none of the above is set, use the og-global.png.
   *
   * @covers ::buildUri
   */
  public function testTheBuildUriLogic(): void {
    // Create the necessary files and media entities for testing.
    $liftup_file = $this->createTestFile('public://liftup.jpg');
    $liftup_media = $this->createImageMedia($liftup_file);
    $node_image_file = $this->createTestFile('public://node-image.jpg');
    $node_image_media = $this->createImageMedia($node_image_file);
    $hero_file = $this->createTestFile('public://hero.jpg');
    $hero_media = $this->createImageMedia($hero_file);
    $org_file = $this->createTestFile('public://org-default.jpg');

    // Create the hero entity.
    /** @var \Drupal\helfi_paragraphs_hero\Entity\Hero $hero */
    $hero = Hero::create([
      'type' => 'hero',
      'field_hero_image' => $hero_media->id(),
      'field_hero_design' => 'with-image-right',
    ]);
    $hero->save();

    // Create the organization term.
    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = Term::create([
      'vid' => 'organization',
      'name' => 'Org',
      'field_default_image' => [
        'target_id' => $org_file->id(),
      ],
    ]);
    $term->save();

    // Create the node with liftup image, node image, hero image and
    // organization default image.
    $node = $this->createNode([
      'field_liftup_image' => $liftup_media->id(),
      'field_image' => $node_image_media->id(),
      'field_hero' => [
        [
          'target_id' => $hero->id(),
          'target_revision_id' => $hero->getRevisionId(),
        ],
      ],
      'field_organization' => $term->id(),
    ]);

    // Assert that liftup image is used when it is set.
    $uri = $this->builder->buildUri($node);
    $this->assertSame($liftup_file->getFileUri(), $uri);

    // Assert that node image is used when liftup image is missing.
    $node->set('field_liftup_image', NULL)->save();
    $uri = $this->builder->buildUri($node);
    $this->assertSame($node_image_file->getFileUri(), $uri);

    // Assert that hero image is used when node image is missing.
    $node->set('field_image', NULL)->save();
    $uri = $this->builder->buildUri($node);
    $this->assertSame($hero_file->getFileUri(), $uri);

    // Assert that organization default image is used when other images
    // are missing.
    $node->set('field_hero', NULL)->save();
    $uri = $this->builder->buildUri($node);
    $this->assertSame($org_file->getFileUri(), $uri);

    // Assert that nodeImageBuilder returns null, when no images are available.
    $node->set('field_organization', NULL)->save();
    $uri = $this->builder->buildUri($node);
    $this->assertNull($uri);
  }

  /**
   * Add a media reference field to a bundle.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle ID.
   * @param string $field_name
   *   Field machine name.
   */
  private function addMediaReferenceField(string $entity_type, string $bundle, string $field_name): void {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => 'entity_reference',
        'settings' => [
          'target_type' => 'media',
        ],
      ])->save();
    }

    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => $field_name,
        'settings' => [
          'handler' => 'default',
          'handler_settings' => [],
        ],
      ])->save();
    }
  }

  /**
   * Add a Hero reference field (paragraph reference) to a bundle.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle ID.
   * @param string $field_name
   *   Field machine name.
   */
  private function addHeroReferenceField(string $entity_type, string $bundle, string $field_name): void {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => 'entity_reference_revisions',
        'settings' => [
          'target_type' => 'paragraph',
        ],
      ])->save();
    }

    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => $field_name,
        'settings' => [
          'handler' => 'default:paragraph',
          'handler_settings' => [
            'target_bundles' => [
              'hero' => 'hero',
            ],
          ],
        ],
      ])->save();
    }
  }

  /**
   * Adds field_hero_design to hero paragraph bundle.
   */
  private function addHeroDesignField(): void {
    // Field storage (paragraph.field_hero_design).
    if (!FieldStorageConfig::loadByName('paragraph', 'field_hero_design')) {
      FieldStorageConfig::create([
        'field_name' => 'field_hero_design',
        'entity_type' => 'paragraph',
        'type' => 'list_string',
        'settings' => [
          'allowed_values' => [],
          'allowed_values_function' => 'helfi_paragraphs_hero_design_allowed_values',
        ],
        'module' => 'options',
        'cardinality' => 1,
        'translatable' => TRUE,
      ])->save();
    }

    // Field instance on hero bundle
    // (field.field.paragraph.hero.field_hero_design).
    if (!FieldConfig::loadByName('paragraph', 'hero', 'field_hero_design')) {
      FieldConfig::create([
        'field_name' => 'field_hero_design',
        'entity_type' => 'paragraph',
        'bundle' => 'hero',
        'label' => 'Hero design',
        'required' => FALSE,
        'translatable' => TRUE,
        'settings' => [],
      ])->save();
    }
  }

  /**
   * Add an organization term reference field to a bundle.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle ID.
   * @param string $field_name
   *   Field machine name.
   */
  private function addOrganizationReferenceField(string $entity_type, string $bundle, string $field_name): void {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => 'entity_reference',
        'settings' => [
          'target_type' => 'taxonomy_term',
        ],
      ])->save();
    }

    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => $field_name,
        'settings' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => [
            'target_bundles' => [
              'organization' => 'organization',
            ],
          ],
        ],
      ])->save();
    }
  }

  /**
   * Add a file reference field to a bundle.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle ID.
   * @param string $field_name
   *   Field machine name.
   */
  private function addFileReferenceField(string $entity_type, string $bundle, string $field_name): void {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => 'file',
      ])->save();
    }

    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => $field_name,
      ])->save();
    }
  }

  /**
   * Create the media type "image" and its field_media_image field.
   */
  private function createImageMediaType(): void {
    if (!MediaType::load('image')) {
      $media_type = MediaType::create([
        'id' => 'image',
        'label' => 'Image',
        'source' => 'image',
        'new_revision' => TRUE,
        'queue_thumbnail_downloads' => FALSE,
        'source_configuration' => [
          'source_field' => 'field_media_image',
        ],
      ]);
      $media_type->save();
    }

    if (!FieldStorageConfig::loadByName('media', 'field_media_image')) {
      FieldStorageConfig::create([
        'field_name' => 'field_media_image',
        'entity_type' => 'media',
        'type' => 'image',
        'settings' => [
          'uri_scheme' => 'public',
        ],
      ])->save();
    }

    if (!FieldConfig::loadByName('media', 'image', 'field_media_image')) {
      FieldConfig::create([
        'field_name' => 'field_media_image',
        'entity_type' => 'media',
        'bundle' => 'image',
        'label' => 'Image',
      ])->save();
    }
  }

  /**
   * Create a node with optional field values.
   *
   * @param array $field_values
   *   Field values keyed by field name.
   *
   * @return \Drupal\node\Entity\Node
   *   The created node.
   */
  private function createNode(array $field_values = []): Node {
    $values = [
      'type' => 'page',
      'title' => 'Test node',
      'status' => NodeInterface::PUBLISHED,
    ] + $field_values;

    /** @var \Drupal\node\Entity\Node $node */
    $node = Node::create($values);
    $node->save();

    return $node;
  }

  /**
   * Create a simple file entity.
   *
   * @param string $uri
   *   File URI.
   *
   * @return \Drupal\file\FileInterface
   *   The created file.
   */
  private function createTestFile(string $uri): FileInterface {
    /** @var \Drupal\file\Entity\File $file */
    $file = File::create(['uri' => $uri]);
    $file->save();
    return $file;
  }

  /**
   * Create an image media entity for a file.
   *
   * @param \Drupal\file\FileInterface $file
   *   File entity.
   *
   * @return \Drupal\media\Entity\Media
   *   The created media entity.
   */
  private function createImageMedia(FileInterface $file): Media {
    /** @var \Drupal\media\Entity\Media $media */
    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Test image',
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => 'Alt text',
      ],
      'status' => TRUE,
    ]);
    $media->save();
    return $media;
  }

}
