<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\ExistingSite;

use Behat\Mink\Element\NodeElement;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Scans bundled configuration.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
class BreadcrumbTest extends ExistingSiteBase {

  use DrushTestTrait;

  /**
   * Nodes for breadcrumb test.
   *
   * @var Node[]
   */
  private array $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $menulinkParent = NULL;
    foreach([1,2] as $key => $value) {
      $title = "Level $value page - en";

      $this->nodes[$key] = Node::create([
        'type' => 'page',
        'title' => $title,
        'status' => 1,
      ]);
      $this->nodes[$key]->save();

      $menulinkSettings = [
        'title' => $title,
        'link' => [
          'uri' => 'entity:node/' . $this->nodes[$key]->id(),
        ],
        'menu_name' => 'main',
        'enabled' => 1,
      ];

      // Add nesting to the menu tree.
      if ($menulinkParent) {
        $linkSettings['parent'] = $menulinkParent;
      }
      $link = MenuLinkContent::create($menulinkSettings);
      $link->save();

      $menulinkParent = $link->getPluginId();
    }
  }

  /**
   * Make sure we flush caches once we're done installing modules.
   */
  #[Test]
  public function testBreadcrumb(): void {
    $this->drupalGet($this->nodes[1]->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $elements = $this->getSession()->getPage()->findAll('css', '.hds-breadcrumb ol li');

    $titles = array_map(function (NodeElement $el): string { return $el->getText();}, $elements) ?? [];
    $uniqueTitles = array_unique($titles, SORT_STRING);

    // Test for duplicates.
    $this->assertCount(count($uniqueTitles), $titles);

    // Assert the breadcrumb items.
    $this->assertTrue(in_array('Level 1 page - en', $titles));
    $this->assertTrue(in_array('Level 2 page - en', $titles));
  }

}
