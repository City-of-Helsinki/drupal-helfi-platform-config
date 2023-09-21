<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Menu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\menu_block_current_language\Event\Events;
use Drupal\menu_block_current_language\Event\HasTranslationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * #UHF-8910 Filter menu items without enabled translation on current langcode.
 */
final class FilterDisabledTranslations implements EventSubscriberInterface {


  /**
   * The constructor.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param LanguageManagerInterface $languageManager
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
  public static function getSubscribedEvents(): array
  {
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
    $link = $event->getLink();
    $metadata = $link->getMetaData();

    if (empty($metadata['entity_id'])) {
      return;
    }

    $entity = $this->entityTypeManager->getStorage('menu_link_content')
      ->load($metadata['entity_id']);
    $current_language = $this->languageManager
      ->getCurrentLanguage()
      ->getId();

    // Entity has translation but it is not enabled, hide it.
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
