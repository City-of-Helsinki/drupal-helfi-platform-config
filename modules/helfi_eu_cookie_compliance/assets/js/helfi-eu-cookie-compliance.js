'use strict';

((Drupal) => {

  // Global cookie consent status object.
  Drupal.cookieConsent = {
    initialized: () => {
      return Drupal.eu_cookie_compliance;
    },
    loadFunction: (loadFunction) => {
      if (typeof loadFunction === 'function') {
        // Load when cookie settings are changed.
        document.addEventListener('eu_cookie_compliance.changeStatus', loadFunction);
        // Load on page load.
        document.addEventListener('DOMContentLoaded', loadFunction);
      }
    },
    getConsentStatus: (categories) => {
      if (typeof Drupal.eu_cookie_compliance === 'undefined') {
        return;
      }

      let hasAgreed = true
      categories.map((category) => {
        // Fallback for old naming convention.
        if (category === 'preferences') category = 'preference';
        if (!Drupal.eu_cookie_compliance.hasAgreed(category)) hasAgreed = false;
      })
      return hasAgreed;
    },
    setAcceptedCategories: (categories) => {
      categories.map((category) => {
        Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), category ]);
      });
    },
  };
})(Drupal);
