<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Hooks;

use Drupal\block\Entity\Block;
use Drupal\helfi_platform_config\Hook\ChatBlockHooks;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the chat block selection on a page.
 */
#[Group('helfi_platform_config')]
#[RunTestsInSeparateProcesses]
final class ChatBlockHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'path_alias',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->container->get('theme_installer')->install(['stark']);

    $request_stack = $this->container->get('request_stack');
    $request = Request::create('/a/b/c');
    $request->setSession($request_stack->getCurrentRequest()->getSession());
    $request_stack->push($request);
  }

  /**
   * Place a chat block with the given visibility paths.
   *
   * @param string $id
   *   The block ID.
   * @param string $pages
   *   The visibility paths.
   */
  private function placeChatBlock(string $id, string $pages): void {
    $visibility = [];
    if ($pages) {
      $visibility['request_path'] = [
        'id' => 'request_path',
        'negate' => FALSE,
        'pages' => $pages,
      ];
    }

    Block::create([
      'id' => $id,
      'theme' => 'stark',
      'region' => 'content',
      'plugin' => 'telia_ace_widget',
      'settings' => [
        'id' => 'telia_ace_widget',
        'label' => $id,
        'provider' => 'helfi_platform_config',
        'chat_id' => $id,
        'chat_title' => '',
        'chat_icon' => 'speechbubble-text',
      ],
      'visibility' => $visibility,
    ])->save();
  }

  /**
   * Run the page preprocess with the given blocks in the attachments region.
   *
   * @param string[] $block_ids
   *   The block IDs in render order.
   *
   * @return array<int|string>
   *   Returns the block IDs left in the attachments region.
   */
  private function preprocessPage(array $block_ids): array {
    $variables = ['page' => ['attachments' => []]];
    foreach ($block_ids as $block_id) {
      $variables['page']['attachments'][$block_id] = ['#markup' => $block_id];
    }

    $hooks = new ChatBlockHooks(
      $this->container->get('entity_type.manager'),
      $this->container->get('plugin.manager.condition'),
    );
    $hooks->preprocessPage($variables);

    return array_keys($variables['page']['attachments']);
  }

  /**
   * The chat block with the longest matching path is kept.
   */
  public function testLongestMatchingPathIsKept(): void {
    $this->placeChatBlock('site_chat', '/a/*');
    $this->placeChatBlock('page_chat', "/d\r\n/a/b/c");

    $this->assertSame(['page_chat'], $this->preprocessPage(['site_chat', 'page_chat']), 'The page chat is kept.');
  }

  /**
   * A chat block with a matching path is kept over one without paths.
   */
  public function testMatchingPathIsKeptOverNoPaths(): void {
    $this->placeChatBlock('page_chat', '/a/*');
    $this->placeChatBlock('site_chat', '');

    $this->assertSame(['page_chat'], $this->preprocessPage(['site_chat', 'page_chat']), 'The page chat is kept.');
  }

  /**
   * Other blocks are kept next to the chat block.
   */
  public function testOtherBlocksAreKept(): void {
    $this->placeChatBlock('page_chat', '/a/b/c');

    $this->assertSame(['menu', 'page_chat'], $this->preprocessPage(['menu', 'page_chat']), 'The other block is kept.');
  }

}
