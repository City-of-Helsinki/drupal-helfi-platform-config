<?php

declare(strict_types=1);

namespace Drupal\helfi_paragraphs_content_cards\Entity;

use Drupal\Core\Language\LanguageInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * Bundle class for content_cards paragraph.
 */
class ContentCards extends Paragraph implements ParagraphInterface {

  use TranslatorTrait;

  /**
   * Get the block visibility.
   *
   * @return bool
   *   Return block visibility as boolean.
   */
  public function getBlockVisibility(): bool {
    // Show the block always to logged-in user.
    if (!\Drupal::currentUser()->isAnonymous()) {
      return TRUE;
    }

    // If there is no references in the field there is no reason to continue.
    if ($this->get('field_content_cards_content')->isEmpty()) {
      return FALSE;
    }

    $references = $this->get('field_content_cards_content');
    $storage = $this->entityTypeManager()->getStorage('node');
    $current_language = $this->languageManager()->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    $published = FALSE;

    foreach ($references as $reference) {
      $referenced_content_id = $reference->getValue()['target_id'];
      /** @var \Drupal\node\NodeInterface $referenced_entity */
      $referenced_entity = $storage->load($referenced_content_id);

      if ($referenced_entity->getTranslation($current_language->getId())->isPublished()) {
        $published = TRUE;
        break;
      }
    }
    return $published;
  }

}
