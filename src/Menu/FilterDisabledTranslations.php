<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Menu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\menu_block_current_language\Event\Events;
use Drupal\menu_block_current_language\Event\HasTranslationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * UHF-8910 Filter menu items without enabled translation on current langcode.
 */
final class FilterDisabledTranslations implements EventSubscriberInterface {

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The admin context.
   */
  public function __construct(
    readonly private EntityTypeManagerInterface $entityTypeManager,
    readonly private LanguageManagerInterface $languageManager,
    readonly private AdminContext $adminContext,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      Events::HAS_TRANSLATION => [
        ['filter'],
      ],
    ];
  }

  /**
   * Responds to Events::HAS_TRANSLATION event.
   *
   * @param \Drupal\menu_block_current_language\Event\HasTranslationEvent $event
   *   The event subscribed to.
   */
  public function filter(HasTranslationEvent $event): void {
    // Disable for admin routes, otherwise the menu UI hides unpublished
    // links.
    if (!$event->hasTranslation() || $this->adminContext->isAdminRoute()) {
      return;
    }

    $link = $event->getLink();
    $metadata = $link->getMetaData();

    if (empty($metadata['entity_id'])) {
      return;
    }

    $current_language = $this->languageManager
      ->getCurrentLanguage()
      ->getId();

    $entity = $this->entityTypeManager->getStorage('menu_link_content')
      ->load($metadata['entity_id']);

    // MenuLinkContent entity has translation which isn't enabled, hide it.
    if (
      $entity->getMenuName() == 'main' &&
      $entity->hasTranslation($current_language)
    ) {
      $translation_enabled = (bool) $entity->getTranslation($current_language)
        ->content_translation_status
        ->value;
      if (!$translation_enabled) {
        $event->setHasTranslation(FALSE);
      }
    }
  }

}
