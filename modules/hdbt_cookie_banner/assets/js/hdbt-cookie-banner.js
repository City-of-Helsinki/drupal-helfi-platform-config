'use strict';

((Drupal, drupalSettings) => {

  // Global cookie consent status object.
  Drupal.cookieConsent = {
    initialized: () => {
      return window && window.hds.cookieConsent;
    },
    loadFunction: (loadFunction) => {
      if (typeof loadFunction === 'function') {
        document.addEventListener('hds_cookieConsent_ready', loadFunction);
      }
    },
    getConsentStatus: (categories) => {
      return window &&
        window.hds.cookieConsent &&
        window.hds.cookieConsent.getConsentStatus(categories);
    },
    setAcceptedCategories: (categories) => {
      if (Drupal.cookieConsent.initialized()) {
        window.hds.cookieConsent.setGroupsStatusToAccepted(categories);
      }
    },
  };

  Drupal.behaviors.hdbt_cookie_banner = {
    attach: function () {
      if (
        typeof window.hds !== 'undefined' &&
        typeof window.hds.CookieConsentCore !== 'undefined'
      ) {
        const apiUrl = drupalSettings.hdbt_cookie_banner.apiUrl;
        const options = {
          language: drupalSettings.hdbt_cookie_banner.langcode,
          theme: drupalSettings.hdbt_cookie_banner.theme,
          settingsPageSelector: drupalSettings.hdbt_cookie_banner.settingsPageSelector,
          spacerParentSelector: '.footer',
        };

        window.hds.CookieConsentCore.create(apiUrl, options);
      }
    }
  }
})(Drupal, drupalSettings);
