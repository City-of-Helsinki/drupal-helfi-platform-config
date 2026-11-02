<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_users\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Base class for favorite kernel tests.
 */
abstract class FavoriteKernelTestBase extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Permissions of a content editor who can use favorites.
   */
  protected const array EDITOR_PERMISSIONS = [
    'access content',
    'edit any page content',
    'use favorites',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'language',
    'helfi_users',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('helfi_users', ['helfi_users_favorite']);
    $this->installConfig(['system', 'filter', 'node', 'language']);
    ConfigurableLanguage::createFromLangcode('fi')->save();

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $this->container->get('router.builder')->rebuild();

    // Create a superuser so test users are regular users.
    $this->createUser();
  }

  /**
   * Creates a page node.
   *
   * @return \Drupal\node\NodeInterface
   *   The page node.
   */
  protected function createPage(): NodeInterface {
    $node = Node::create([
      'type' => 'page',
      'title' => $this->randomMachineName(),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Adds a Finnish translation to the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return \Drupal\node\NodeInterface
   *   The Finnish translation.
   */
  protected function addFinnishTranslation(NodeInterface $node): NodeInterface {
    $translation = $node->addTranslation('fi', ['title' => $this->randomMachineName()]);
    $translation->save();
    return $translation;
  }

  /**
   * Creates a content editor who can use favorites.
   *
   * @return \Drupal\user\UserInterface
   *   The editor.
   */
  protected function createEditor(): UserInterface {
    return $this->createUser(self::EDITOR_PERMISSIONS);
  }

  /**
   * Clicks the favorite toggle in the rendered markup.
   *
   * @param string $html
   *   The rendered markup.
   *
   * @return string
   *   The swapped toggle markup.
   */
  protected function clickFavoriteToggle(string $html): string {
    if (!preg_match('/data-hx-post="([^"]+)"/', $html, $matches)) {
      $this->fail('The markup has the favorite toggle.');
    }

    $request = Request::create(html_entity_decode($matches[1]), 'POST');
    $request->setSession($this->container->get('request_stack')->getCurrentRequest()->getSession());
    $response = $this->container->get('http_kernel')->handle($request, HttpKernelInterface::MAIN_REQUEST, FALSE);
    assert($response instanceof Response);

    return (string) $response->getContent();
  }

}
