(function ($, Drupal) {
  'use strict';

  let loadReactAndShare = () => {
    if (Drupal.cookieConsent.getConsentStatus(['statistics'])) {
      window.askem = {
        settings: {
          apiKey: drupalSettings.reactAndShareApiKey,
          disableFonts: true,
        }
      };

      if (drupalSettings.siteName !== undefined) {
        window.askem.settings.categories = [drupalSettings.siteName]
      }

      const scriptElement = document.createElement('script');
      scriptElement.integrity = "sha384-IyR9lHXB7FlXbifApQRUdDvlfxWnp7yOM7JP1Uo/xn4bIUlbRgxYOfEk80efwlD8";
      scriptElement.crossOrigin = 'anonymous';
      scriptElement.src = 'https://cdn.askem.com/plugin/askem.js';

      document.body.appendChild(scriptElement);

      $('.js-askem__container .js-askem-cookie-compliance').hide();
      $('.js-askem__container .askem').show();
    }
    else {
      $('.js-askem__container .js-askem-cookie-compliance').show();
    }

    // Only load once.
    loadReactAndShare = function () {};
  };

  if (Drupal.cookieConsent.initialized()) {
    loadReactAndShare();
  } else {
    Drupal.cookieConsent.loadFunction(loadReactAndShare);
  }
})(jQuery, Drupal);
