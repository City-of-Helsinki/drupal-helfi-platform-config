<?php

declare(strict_types = 1);

namespace Drupal\helfi_platform_config\Plugin\LanguageNegotiation;

use Drupal\language\LanguageNegotiationMethodBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class for identifying language from the user preferences.
 *
 * @LanguageNegotiation(
 *   id = \Drupal\helfi_platform_config\Plugin\LanguageNegotiation\LanguageNegotiationAdmin::METHOD_ID,
 *   weight = -4,
 *   name = @Translation("Admin"),
 *   description = @Translation("Follow the user's administration language preference.")
 * )
 */
class LanguageNegotiationAdmin extends LanguageNegotiationMethodBase {

  /**
   * The language negotiation method id.
   */
  public const METHOD_ID = 'language-admin-preference';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL): ?string {
    $langcode = NULL;

    if ($this->languageManager && $this->currentUser->isAuthenticated()) {
      $preferred_langcode = $this->currentUser->getPreferredAdminLangcode();
      $languages = $this->languageManager->getLanguages();
      if (!empty($preferred_langcode) && isset($languages[$preferred_langcode])) {
        $langcode = $preferred_langcode;
      }
    }

    return $langcode;
  }

}
