<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\ExistingSite;

use Behat\Mink\Element\NodeElement;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\Tests\helfi_api_base\Functional\ExistingSiteTestBase;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

/**
 * Scans bundled configuration.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class BreadcrumbTest extends ExistingSiteTestBase {

  use DrushTestTrait;

  /**
   * Nodes for breadcrumb test.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  private array $nodes = [];

  /**
   * The label of the project.
   *
   * @var string|null
   */
  private string|null $label = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    try {
      $project = $this->container->get('helfi_api_base.environment_resolver')->getActiveProject();

      $this->label = (string) $this->container
        ->get('string_translation')
        ->translate($project->label()->getUntranslatedString(), [], ['langcode' => 'en', 'context' => 'Project label']);
    }
    catch (\Exception) {
    }

    $menulinkParent = NULL;
    foreach ([1, 2] as $key => $value) {
      $title = "Level $value page - en";

      $this->nodes[$key] = Node::create([
        'type' => 'page',
        'title' => $title,
        'langcode' => 'en',
        'status' => 1,
      ]);
      $this->nodes[$key]->save();

      $menulinkSettings = [
        'title' => "$title edited",
        'link' => [
          'uri' => 'entity:node/' . $this->nodes[$key]->id(),
        ],
        'menu_name' => 'main',
        'enabled' => 1,
      ];

      // Add nesting to the menu tree.
      if ($menulinkParent) {
        $menulinkSettings['parent'] = $menulinkParent;
      }
      $link = MenuLinkContent::create($menulinkSettings);
      $link->save();

      $this->nodes[$key]->save();

      $menulinkParent = $link->getPluginId();
    }
  }

  /**
   * Test the breadcrumb.
   */
  #[Test]
  public function testBreadcrumb(): void {
    $this->drupalGet($this->nodes[1]->getTranslation('en')->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $elements = $this->getSession()->getPage()->findAll('css', '.hds-breadcrumb ol li');

    $titles = array_map(fn (NodeElement $el): string => strtolower($el->getText()), $elements);
    $uniqueTitles = array_unique($titles);

    // Test for duplicates.
    $this->assertCount(count($uniqueTitles), $titles);

    // Assert the breadcrumb items.
    $this->assertTrue(in_array(strtolower($this->label), $titles), 'Site name found from breadcrumb');
    $this->assertTrue(in_array('level 1 page - en edited', $titles), 'Level 1 node menu title found from breadcrumb');
    $this->assertTrue(in_array('level 2 page - en edited', $titles), 'Level 2 node menu title found from breadcrumb');
  }

}
