'use strict';

class UnapprovedItemError extends Error {
  constructor(message, name) {
    super(message)
    this.name = name
  }
}

((Drupal, drupalSettings) => {

  // Global cookie consent status object.
  Drupal.cookieConsent = {
    initialized: () => {
      return window && window.hds && window.hds.cookieConsent;
    },
    loadFunction: (loadFunction) => {
      if (typeof loadFunction === 'function') {
        window.addEventListener('hds-cookie-consent-ready', loadFunction);
      }
    },
    getConsentStatus: (categories) => {
      return Drupal.cookieConsent.initialized() &&
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
      // The hds-cookie-consent.min.js should be loaded before this script.
      // Check if the script is loaded.
      if (
        typeof window.hds !== 'undefined' &&
        typeof window.hds.CookieConsentCore !== 'undefined'
      ) {
        const apiUrl = drupalSettings.hdbt_cookie_banner.apiUrl;
        const options = {
          language: drupalSettings.hdbt_cookie_banner.langcode,
          theme: drupalSettings.hdbt_cookie_banner.theme,
          settingsPageSelector: drupalSettings.hdbt_cookie_banner.settingsPageSelector,
          spacerParentSelector: drupalSettings.hdbt_cookie_banner.spacerParentSelector || '.footer',
        };
        window.hds.CookieConsentCore.create(apiUrl, options);
      }
      else {
        console.warn('The hds-cookie-consent.min.js script is not loaded. Check the HDBT cookie banner configurations.');
      }

      // A click event for opening the cookie consent banner from correct groups.
      window.hdsCookieConsentClickEvent = function hdsCookieConsentClickEvent(event, element) {
        const groups = element.getAttribute('data-cookie-consent-groups')
          .split(',')
          .map(group => group.trim());

        if (
          Drupal.cookieConsent.initialized() &&
          typeof window.hds.cookieConsent.openBanner === 'function'
        ) {
          window.hds.cookieConsent.openBanner(groups);
          event.preventDefault();
        }
      };
    }
  }

  // Attach a behavior to capture unapproved cookies with Sentry.
  Drupal.behaviors.unapprovedCookies = {
    attach: function attach() {
      window.addEventListener(
        'hds-cookie-consent-unapproved-item-found',
        (e) => {
          if (typeof window.Sentry === 'undefined') {
            return;
          }
          const { storageType, keys, acceptedGroups } = e.detail

          // Alphabetize the keys array
          const sortedKeys = keys.sort();

          // Sentry requires a unique name for each error in order to record
          // each found unapproved item per type.
          const name = `Unapproved ${storageType}`
          const message = `Found: ${sortedKeys.join(', ')}`

          // Capture the error with Sentry and send a message with the
          // unapproved items so that they can be searched in Sentry.
          window.Sentry.captureException(new UnapprovedItemError(message, name), {
            level: 'warning',
            tags: {
              approvedCategories: acceptedGroups.join(', '),
            },
            extra: {
              storageType,
              cookieNames: sortedKeys,
              approvedCategories: acceptedGroups,
            },
          })
        }
      )
    },
  }
})(Drupal, drupalSettings);
