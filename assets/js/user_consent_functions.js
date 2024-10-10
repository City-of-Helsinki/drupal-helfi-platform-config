(function (Drupal) {
  'use strict';

  // @todo UHF-8650: EU Cookie Compliance module will be removed.
  // @todo UHF-8650: Convert the following code to support HDS cookie banner.
  window.chat_user_consent = {
    retrieveUserConsent: () => (Drupal.eu_cookie_compliance.hasAgreedWithCategory('chat')),
    confirmUserConsent: () => {
      if (Drupal.eu_cookie_compliance.hasAgreedWithCategory('chat')) return;
      Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), 'chat' ]);
    }
  };

})(Drupal);
