'use strict';

class UnapprovedItemError extends Error {
  constructor(message, name) {
    super(message)
    this.name = name
  }
}

((Drupal, drupalSettings) => {

  let unapprovedCookiesInitialized = false;

  // Attach a behavior to capture unapproved cookies with Sentry.
  Drupal.behaviors.unapprovedCookies = {
    attach: function attach(context) {
      // Run only once for the full document.
      if (context !== document || unapprovedCookiesInitialized) {
        return;
      }

      unapprovedCookiesInitialized = true;

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
    },
  }
})(Drupal, drupalSettings);
