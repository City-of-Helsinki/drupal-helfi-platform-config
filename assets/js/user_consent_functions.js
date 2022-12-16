(function (Drupal) {
  'use strict';

  window.chat_user_consent = {
    retrieveUserConsent: () => (Drupal.eu_cookie_compliance.hasAgreedWithCategory('chat')),
    confirmUserConsent: () => {
      if (Drupal.eu_cookie_compliance.hasAgreedWithCategory('chat')) return;
      Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), 'chat' ]);
    }
  };

})(Drupal);
