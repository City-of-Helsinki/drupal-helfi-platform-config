(function (Drupal) {
  'use strict';

  window.chat_user_consent = {
    retrieveUserConsent: () => (Drupal.cookieConsent.getConsentStatus(['chat'])),
    confirmUserConsent: () => {
      if (Drupal.cookieConsent.getConsentStatus(['chat'])) return;
      Drupal.cookieConsent.setAcceptedCategories(['chat']);
    }
  };

})(Drupal);
