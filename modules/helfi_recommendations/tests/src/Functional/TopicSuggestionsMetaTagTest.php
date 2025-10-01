<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;

/**
 * Taxonomy related tests.
 *
 * @group helfi_platform_config
 */
class TopicSuggestionsMetaTagTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_user_roles',
    'helfi_recommendations',
  ];

  /**
   * Tests the topic suggestions meta tag.
   */
  public function testTopicSuggestionsMetaTag() : void {
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

    $vocabulary = Vocabulary::create([
      'name' => 'Test topics vocabulary',
      'vid' => 'test_topics',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $vocabulary->save();

    $term1 = Term::create([
      'vid' => $vocabulary->id(),
      'name' => 'Test topic, with a comma in it',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $term1->save();

    $term2 = Term::create([
      'vid' => $vocabulary->id(),
      'name' => 'Another test topic',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $term2->save();

    $node = Node::create([
      'type' => 'test_node_bundle',
      'title' => $this->randomString(),
      'test_keywords' => SuggestedTopics::create([
        'keywords' => [
          ['entity' => $term1, 'score' => 0.8],
          ['entity' => $term2, 'score' => 0.2],
        ],
      ]),
    ]);
    $node->save();

    $nodeUrl = $node->toUrl()->toString();
    $this->drupalGet($nodeUrl);

    $xpath = $this->xpath("//meta[@name='helfi_suggested_topics']");
    $this->assertEquals(1, count($xpath));
    $this->assertEquals('"Test topic, with a comma in it","Another test topic"', $xpath[0]->getAttribute('content'));
  }

}
