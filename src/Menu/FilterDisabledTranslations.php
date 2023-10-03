<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Menu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\Entity\Node;
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
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    readonly private EntityTypeManagerInterface $entityTypeManager,
    readonly private LanguageManagerInterface $languageManager
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
   * @param Drupal\menu_block_current_language\Event\Events $event
   *   The event subscribed to.
   */
  public function filter(HasTranslationEvent $event): void {
    if (!$event->hasTranslation()) {
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

    // Hide links with node translation unpublished
    if ($link->getRouteName() == 'entity.node.canonical') {
      $params = $link->getRouteParameters();
      $nid = $params['node'];
      $node = Node::load($nid);

      if ($node->hasTranslation($current_language)) {
          $translated_node = $node->getTranslation($current_language);
          $event->setHasTranslation($translated_node->isPublished());
          return;
      }
    }

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
