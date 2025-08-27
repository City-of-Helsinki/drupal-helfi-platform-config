(function ($, Drupal, drupalSettings) {
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

      const errorTracker = {};
      const errorFrequency = 10;

      // Report errors to Sentry but throttle them so that only every tenth
      // is sent to avoid spamming logs.
      function reportToSentryThrottled(errorKey, error) {
        if (!errorTracker[errorKey]) {
          errorTracker[errorKey] = { count: 0 };
        }

        errorTracker[errorKey].count++;

        if (errorTracker[errorKey].count === 1 ||
          errorTracker[errorKey].count % errorFrequency === 0) {
          Sentry.captureException(error);
        }
      }

      function handleScriptError(event) {
        const script = event.target;
        const src = script.src || "inline-script";
        const err = new Error(`Askem script failed to load or integrity check failed: ${src}`);

        reportToSentryThrottled(src, err);
      }

      const scriptElement = document.createElement('script');
      scriptElement.integrity = "sha384-fxefL+yfJDZZIDd0dpHPI0WdzOeSh9QecaVam/sAYq2NtZB2qCXzX/7r7cWE+Xef";
      scriptElement.crossOrigin = 'anonymous';
      scriptElement.src = 'https://cdn.askem.com/plugin/askem.js';

      // Use the error monitoring only if it is set on.
      if (drupalSettings.askemMonitoringEnabled) {
        scriptElement.onerror = handleScriptError;
      }

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
})(jQuery, Drupal, drupalSettings);
