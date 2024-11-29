'use strict';

((Drupal, drupalSettings) => {

  // Global cookie consent status object.
  Drupal.cookieConsent = {
    initialized: () => {
      return window && window.hds && window.hds.cookieConsent;
    },
    loadFunction: (loadFunction) => {
      if (typeof loadFunction === 'function') {
        document.addEventListener('hds_cookieConsent_ready', loadFunction);
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
          spacerParentSelector: '.footer',
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
          const { type, keys, consentedGroups } = e.detail

          if (window.Sentry) {
            // Sentry requires a unique name for each error in order to record
            // each found unapproved item per type.
            const name = `Unapproved ${type}`
            const message = `Found: ${keys.join(', ')}`

            class UnapprovedItemError extends Error {
              constructor(message) {
                super(message)
                this.name = name
              }
            }

            // Capture the error with Sentry and send a message with the
            // unapproved items so that they can be searched in Sentry.
            window.Sentry.captureException(new UnapprovedItemError(message), {
              level: 'warning',
              tags: {
                approvedCategories: consentedGroups.join(', '),
              },
              extra: {
                type,
                cookieNames: keys,
                approvedCategories: consentedGroups,
              },
            })
          } else {
            // If Sentry is not defined, throw an error.
            throw new Error('Sentry is not defined')
          }
        }
      )
    },
  }
})(Drupal, drupalSettings);
