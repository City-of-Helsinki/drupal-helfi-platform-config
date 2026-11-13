<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Hook;

use Drupal\block\BlockInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\helfi_platform_config\Plugin\Block\ChatBlockBase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Page hook implementations.
 */
final readonly class PageHooks {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'plugin.manager.condition')]
    private ExecutableManagerInterface $conditionManager,
    #[Autowire(service: 'router.admin_context')]
    private AdminContext $adminContext,
    #[Autowire(service: 'current_user')]
    private AccountInterface $currentUser,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for page templates.
   *
   * @param array $variables
   *   The page variables.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    if ($this->adminContext->isAdminRoute()) {
      return;
    }

    $this->attachHeadingAnchorButtons($variables);
    $this->removeExtraChatBlocks($variables);
  }

  /**
   * Attach the heading anchor buttons for editors.
   *
   * @param array $variables
   *   The page variables.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  private function attachHeadingAnchorButtons(array &$variables): void {
    // Show the buttons only to editors until accessibility is tested.
    if ($this->currentUser->isAuthenticated() && $this->currentUser->hasPermission('administer nodes')) {
      $variables['#attached']['library'][] = 'helfi_platform_config/heading_anchor_buttons';
    }
  }

  /**
   * Keep only the chat block with the longest matching path.
   *
   * @param array $variables
   *   The page variables.
   *
   * @phpstan-param array<string, mixed> $variables
   */
  private function removeExtraChatBlocks(array &$variables): void {
    if (empty($variables['page']['attachments'])) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('block');

    $chats = [];
    $blocks = Element::children($variables['page']['attachments']);

    // Collect the visible chat blocks with their path match length.
    foreach ($blocks as $block_id) {
      $block = $storage->load($block_id);
      if (
        $block instanceof BlockInterface &&
        $block->getPlugin() instanceof ChatBlockBase
      ) {
        $chats[$block_id] = $this->getMatchingPathLength($block);
      }
    }

    // Sort the chat blocks by the longest matching path first.
    arsort($chats);

    // Keep the first chat block and remove the rest.
    foreach (array_slice(array_keys($chats), 1) as $block_id) {
      unset($variables['page']['attachments'][$block_id]);
    }
  }

  /**
   * Get the length of the longest block visibility path matching the page.
   *
   * @param \Drupal\block\BlockInterface $block
   *   The chat block.
   *
   * @return int
   *   Returns the path length, or 0 when no path matches.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function getMatchingPathLength(BlockInterface $block): int {
    $pages = $block->getVisibility()['request_path']['pages'] ?? '';

    $length = 0;
    // Split the visibility paths into lines and check each path
    // against the current page.
    foreach (preg_split('/\R/', $pages, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $page) {
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->conditionManager->createInstance('request_path', ['pages' => $page]);
      if (!$condition->evaluate()) {
        continue;
      }

      // Count the path length without the wildcard.
      $page_length = strlen(rtrim($page, '*'));

      // Keep the longest matching path.
      $length = max($length, $page_length);
    }

    return $length;
  }

}
