((Drupal) => {

  // Global cookie consent status object.
  Drupal.cookieConsent = {
    initialized: () => {
      return window?.hds?.cookieConsent;
    },
    loadFunction: (callback) => {
      if (typeof callback !== 'function') return;

      if (Drupal.cookieConsent.initialized()) {
        callback();
        return;
      }

      window.addEventListener('hds-cookie-consent-ready', callback, {
        once: true,
      });
    },
    getConsentStatus: (categories) => {
      if (!Drupal.cookieConsent.initialized()) return null;
      return window.hds.cookieConsent.getConsentStatus(categories);
    },
    setAcceptedCategories: (categories) => {
      if (Drupal.cookieConsent.initialized()) {
        window.hds.cookieConsent.setGroupsStatusToAccepted(categories);
      }
    },
  };

  Drupal.behaviors.hdbt_cookie_banner = {
    attach: (context, settings) => {
      // Run only once for the full document.
      if (context !== document || window.hdsCookieConsentInitialized) {
        return;
      }

      // The hds-cookie-consent.min.js should be loaded before this script.
      // Check if the script is loaded.
      if (
        typeof window.hds !== 'undefined' &&
        typeof window.hds.CookieConsentCore !== 'undefined'
      ) {
        const apiUrl = settings.hdbt_cookie_banner.apiUrl;
        const options = {
          language: settings.hdbt_cookie_banner.langcode,
          theme: settings.hdbt_cookie_banner.theme,
          settingsPageSelector: settings.hdbt_cookie_banner.settingsPageSelector,
          spacerParentSelector: settings.hdbt_cookie_banner.spacerParentSelector || '.footer',
        };
        window.hdsCookieConsentInitialized = true;
        window.hds.CookieConsentCore.create(apiUrl, options);
      }
      else {
        console.warn('The hds-cookie-consent.min.js script is not loaded. Check the HDBT cookie banner configurations.');
      }

      // A click event for opening the cookie consent banner from correct groups.
      window.hdsCookieConsentClickEvent = function hdsCookieConsentClickEvent(event, element) {
        // Check if the element exists and has the getAttribute method.
        if (!element || typeof element.getAttribute !== 'function') return;

        // Get the groups from the data attribute.
        const rawGroups = element.getAttribute('data-cookie-consent-groups');
        if (!rawGroups) return;

        // Split the groups by comma and trim whitespace.
        const groups = rawGroups
          .split(',')
          .map((group) => group.trim())
          .filter(Boolean);

        // Check if the groups are valid.
        if (groups.length === 0) return;

        // Check if the cookie consent is initialized.
        if (
          !Drupal.cookieConsent?.initialized?.() ||
          !window.hds?.cookieConsent ||
          typeof window.hds.cookieConsent.openBanner !== 'function'
        ) {
          return;
        }

        // Open the cookie consent banner with selected groups.
        window.hds.cookieConsent.openBanner(groups);

        // Prevent default action if the event is provided.
        if (event && typeof event.preventDefault === 'function') {
          event.preventDefault();
        }
      };
    }
  }
})(Drupal);
