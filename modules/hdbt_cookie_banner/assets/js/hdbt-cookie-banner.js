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
      const apiUrl = drupalSettings.hdbt_cookie_banner.apiUrl;
      fetch(apiUrl)
      .then(response => response.json())
      .then(jsonData => {
       
        // Function to extract cookie names from each group
        const extractCookiePatterns = (groups) =>
          groups?.flatMap(group => group.cookies.map(cookie => cookie.name)) || [];

        // Collect all allowed cookie name patterns
        const validItems = [
          ...extractCookiePatterns(jsonData.optionalGroups),
          ...extractCookiePatterns(jsonData.requiredGroups),
          ...extractCookiePatterns(jsonData.robotGroups),
        ];

        window.addEventListener(
          'hds-cookie-consent-unapproved-item-found',
          (e) => {
            if (typeof window.Sentry === 'undefined') {
              return;
            }

            const { storageType, keys, acceptedGroups } = e.detail;
            const sortedKeys = keys.sort();

            // Check which keys do not match any pattern in the valid items list
            const unapprovedItems = sortedKeys.filter(
              key => !validItems.some(pattern => key.includes(pattern.replace('*', '')))
            ).sort();

            // Only log if there are unapproved items that are not found in our list
            if (unapprovedItems.length > 0) {
              const name = `Unapproved ${storageType}`;
              const message = `Found: ${unapprovedItems.join(', ')}`;

              window.Sentry.captureException(new UnapprovedItemError(message, name), {
                level: 'warning',
                tags: {
                  approvedCategories: acceptedGroups.join(', '),
                },
                extra: {
                  storageType,
                  missingCookies: unapprovedItems,
                  approvedCategories: acceptedGroups,
                },
              });
            }
          }
        );
      })
      .catch(error => console.error('Failed to fetch JSON:', error));
    },
  }
})(Drupal, drupalSettings);
