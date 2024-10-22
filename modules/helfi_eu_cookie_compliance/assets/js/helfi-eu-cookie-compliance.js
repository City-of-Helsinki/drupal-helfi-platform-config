'use strict';

((Drupal) => {

  // Global cookie consent status object.
  Drupal.cookieConsent = {
    getConsentStatus: (categories) => {
      if (typeof Drupal.eu_cookie_compliance === 'undefined') {
        return;
      }

      categories.forEach(category => {
        if (!Drupal.eu_cookie_compliance.hasAgreed(category)) {
          return false;
        }
      });
      return true;
    },
    setAcceptedCategories: (categories) => {
      Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), categories ]);
    },
  };
})(Drupal);
