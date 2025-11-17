<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Kernel\Entity;

use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\Tests\helfi_recommendations\Kernel\AnnifKernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Prophecy\PhpUnit\ProphecyTrait;
use Drupal\node\Entity\Node;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;

/**
 * Tests the SuggestedTopics entity class.
 *
 * @group helfi_recommendations
 */
class SuggestedTopicsTest extends AnnifKernelTestBase {

  use EnvironmentResolverTrait;
  use NodeCreationTrait;
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
  ];

  /**
   * Test environment.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentEnum
   */
  private EnvironmentEnum $environment = EnvironmentEnum::Local;

  /**
   * The parent node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected Node $parent;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setActiveProject(Project::ETUSIVU, $this->environment);

    $values = [
      'type' => 'node',
      'title' => 'Test Node',
    ];
    $this->parent = Node::create($values);
    $this->parent->save();
  }

  /**
   * Tests the setParentEntity method.
   */
  public function testSetParentEntityMethod(): void {
    /** @var \Drupal\helfi_recommendations\Entity\SuggestedTopics $suggestedTopics */
    $suggestedTopics = SuggestedTopics::create();
    $suggestedTopics->setParentEntity($this->parent);
    $suggestedTopics->save();

    $this->assertEquals($this->parent->id(), $suggestedTopics->get('parent_id')->value);
    $this->assertEquals($this->parent->getEntityTypeId(), $suggestedTopics->get('parent_type')->value);
    $this->assertEquals($this->parent->bundle(), $suggestedTopics->get('parent_bundle')->value);
    $this->assertEquals(Project::ETUSIVU, $suggestedTopics->get('parent_instance')->value);
  }

  /**
   * Tests the getParentEntity method.
   */
  public function testGetParentEntityMethod(): void {
    /** @var \Drupal\helfi_recommendations\Entity\SuggestedTopics $suggestedTopics */
    $suggestedTopics = SuggestedTopics::create();
    $suggestedTopics->setParentEntity($this->parent);
    $suggestedTopics->save();

    $this->assertEquals($this->parent->id(), $suggestedTopics->getParentEntity()->id());
  }

}
