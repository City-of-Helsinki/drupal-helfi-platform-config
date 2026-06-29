<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_node_news_item\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\helfi_node_news_item\SchemaOrg\NewsArticleSchemaBuilder;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the NewsArticle schema.org builder for news content types.
 */
#[Group('helfi_node_news_item')]
#[RunTestsInSeparateProcesses]
final class NewsArticleSchemaBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'field',
    'text',
    'taxonomy',
    'system',
    'helfi_node_news_item',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');

    NodeType::create(['type' => 'news_item', 'name' => 'News item'])->save();
    foreach (['news_tags', 'news_group', 'news_neighbourhoods'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }

    $this->createField('field_short_title', 'string', 'news_item');
    $this->createField('field_lead_in', 'string_long', 'news_item');
    $this->createField('published_at', 'timestamp', 'news_item');
    $this->createField('field_news_item_tags', 'entity_reference', 'news_item', [
      'target_type' => 'taxonomy_term',
    ]);

    // Fields that only the news_article bundle carries.
    $this->createField('field_news_groups', 'entity_reference', 'news_item', [
      'target_type' => 'taxonomy_term',
    ]);
    $this->createField('field_news_neighbourhoods', 'entity_reference', 'news_item', [
      'target_type' => 'taxonomy_term',
    ]);
  }

  /**
   * Builds a NewsArticle graph node and asserts the mapped properties.
   */
  public function testBuildsNewsArticle(): void {
    $tag = Term::create(['vid' => 'news_tags', 'name' => 'Traffic']);
    $tag->save();
    $group = Term::create(['vid' => 'news_group', 'name' => 'Culture']);
    $group->save();
    $neighbourhood = Term::create(['vid' => 'news_neighbourhoods', 'name' => 'Kallio']);
    $neighbourhood->save();

    $node = Node::create([
      'type' => 'news_item',
      'title' => 'Tram line 9 reopens',
      'field_short_title' => 'Tram 9 reopens',
      'field_lead_in' => 'Service resumes Monday.',
      'published_at' => 1718000000,
      'field_news_item_tags' => [$tag->id()],
      'field_news_groups' => [$group->id()],
      'field_news_neighbourhoods' => [$neighbourhood->id()],
      'status' => 1,
    ]);
    $node->save();

    $cacheability = new CacheableMetadata();
    $builder = $this->container->get(NewsArticleSchemaBuilder::class);
    $this->assertTrue($builder->applies($node));
    [$schema] = $builder->build($node, $cacheability);

    $this->assertSame('NewsArticle', $schema['@type']);
    $this->assertStringEndsWith('#newsarticle', $schema['@id']);
    $this->assertStringEndsWith('#webpage', $schema['mainEntityOfPage']['@id']);
    $this->assertSame('Tram line 9 reopens', $schema['headline']);
    $this->assertSame('Tram 9 reopens', $schema['alternativeHeadline']);
    $this->assertSame('Service resumes Monday.', $schema['description']);
    $this->assertSame(['Traffic'], $schema['keywords']);
    $this->assertSame('https://www.hel.fi/#organization', $schema['publisher']['@id']);
    $this->assertSame('https://www.hel.fi/#website', $schema['isPartOf']['@id']);
    $this->assertStringStartsWith('2024-', $schema['datePublished']);
    $this->assertNotEmpty($schema['dateModified']);
    $this->assertSame(['Culture'], $schema['articleSection']);
    $this->assertSame(
      [['@type' => 'Place', 'name' => 'Kallio']],
      $schema['contentLocation'],
    );

    // Cacheability picked up the node, the term and the settings config.
    $this->assertContains('config:helfi_platform_config.schema_settings', $cacheability->getCacheTags());
    $this->assertNotEmpty(array_intersect($node->getCacheTags(), $cacheability->getCacheTags()));
    $this->assertNotEmpty(array_intersect($tag->getCacheTags(), $cacheability->getCacheTags()));
  }

  /**
   * Creates and attaches a field to a bundle.
   *
   * @param string $name
   *   The field machine name.
   * @param string $type
   *   The field type.
   * @param string $bundle
   *   The node bundle to attach to.
   * @param array<string, mixed> $settings
   *   Optional storage settings.
   */
  protected function createField(string $name, string $type, string $bundle, array $settings = []): void {
    if (!FieldStorageConfig::loadByName('node', $name)) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'node',
        'type' => $type,
        'settings' => $settings,
      ])->save();
    }
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $bundle,
    ])->save();
  }

}
