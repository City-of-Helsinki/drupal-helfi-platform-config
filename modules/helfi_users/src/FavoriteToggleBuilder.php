<?php

declare(strict_types=1);

namespace Drupal\helfi_users;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Builds the favorite toggle button.
 */
final readonly class FavoriteToggleBuilder implements TrustedCallbackInterface {

  public function __construct(
    private FavoriteStorage $favoriteStorage,
    private EntityTypeManagerInterface $entityTypeManager,
    private RendererInterface $renderer,
    private AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['lazyBuild'];
  }

  /**
   * Check access to toggle the favorite.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The translation language code.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, ContentEntityInterface $entity, string $langcode): AccessResultInterface {
    // Deny access to missing translations.
    if (!$entity->hasTranslation($langcode)) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    // Allow favorites only for the content that user can edit.
    return AccessResult::allowedIfHasPermission($account, 'use favorites')
      ->andIf($entity->getTranslation($langcode)->access('update', $account, TRUE));
  }

  /**
   * Lazy builder callback for the favorite toggle button.
   *
   * @param string $entityType
   *   The entity type ID.
   * @param string $entityId
   *   The entity ID.
   * @param string $langcode
   *   The translation language code.
   * @param string $display
   *   The button display variant.
   *
   * @return array<string, mixed>
   *   The favorite toggle render array.
   */
  public function lazyBuild(string $entityType, string $entityId, string $langcode, string $display): array {
    $build = [
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];

    // Return if the entity type does not support favorites.
    if (
      !in_array($entityType, FavoriteStorage::ENTITY_TYPES, TRUE) ||
      !$this->entityTypeManager->hasDefinition($entityType)
    ) {
      return $build;
    }

    // Return if the entity does not exist.
    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    if (!$entity instanceof ContentEntityInterface) {
      return $build;
    }

    // Render nothing without access.
    $access = $this->access($this->currentUser, $entity, $langcode);
    if ($access->isAllowed()) {
      $build = $this->build($entity->getTranslation($langcode), $display);
    }
    $this->renderer->addCacheableDependency($build, $access);

    return $build;
  }

  /**
   * Builds the favorite toggle button.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity translation.
   * @param string $display
   *   The button display variant.
   *
   * @return array<string, mixed>
   *   The favorite toggle render array.
   */
  public function build(EntityInterface $entity, string $display): array {
    $build = [
      '#theme' => 'helfi_favorite_toggle',
      '#is_favorite' => $this->favoriteStorage->isFavorite($this->currentUser, $entity),
      '#show_label' => $display === 'label',
      '#attached' => [
        'library' => ['hdbt/hdbt-icons'],
      ],
    ];

    $url = Url::fromRoute('helfi_users.favorite_toggle', [
      'entity_type' => $entity->getEntityTypeId(),
      'entity' => $entity->id(),
      'langcode' => $entity->language()->getId(),
      'display' => $display,
    ]);

    // Post without form values and replace the button with the response.
    (new Htmx())
      ->post($url)
      ->target('this')
      ->select('.favorite-toggle')
      ->swap('outerHTML')
      ->params('none')
      ->applyTo($build);

    // Preserve the URL's CSRF token placeholder and cache metadata
    // so they can be correctly processed when rendering HTMX content.
    BubbleableMetadata::createFromRenderArray($build)
      ->merge($url->toString(TRUE))
      ->addCacheContexts(['user'])
      ->addCacheTags(['helfi_favorite:' . $this->currentUser->id()])
      ->applyTo($build);

    return $build;
  }

}
