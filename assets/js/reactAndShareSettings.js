(($, Drupal, drupalSettings) => {
  let loadReactAndShare = () => {
    if (Drupal.cookieConsent.getConsentStatus(['statistics'])) {
      window.askem = { settings: { apiKey: drupalSettings.reactAndShareApiKey, disableFonts: true } };

      if (drupalSettings.siteName !== undefined) {
        window.askem.settings.categories = [drupalSettings.siteName];
      }

      const errorTracker = {};
      const errorFrequency = 10;

      // Report errors to Sentry but throttle them so that only every tenth
      // is sent to avoid spamming logs.
      // biome-ignore lint/correctness/noUnusedVariables: @todo UHF-12501
      function reportToSentryThrottled(errorKey, error) {
        if (!errorTracker[errorKey]) {
          errorTracker[errorKey] = { count: 0 };
        }

        errorTracker[errorKey].count++;

        if (errorTracker[errorKey].count === 1 || errorTracker[errorKey].count % errorFrequency === 0) {
          Sentry.captureException(error);
        }
      }

      function handleScriptError(event) {
        const script = event.target;
        const src = script.src || 'inline-script';
        const _err = new Error(`Askem script failed to load: ${src}`);

        // reportToSentryThrottled(src, err);
        console.warn('error reporting works');
      }

      const scriptElement = document.createElement('script');
      scriptElement.crossOrigin = 'anonymous';
      scriptElement.src = 'https://cdn.askem.com/plugin/askem.js';

      // Use the error monitoring only if it is set on.
      if (drupalSettings.askemMonitoringEnabled) {
        scriptElement.onerror = handleScriptError;
      }

      document.body.appendChild(scriptElement);

      $('.js-askem__container .js-askem-cookie-compliance').hide();
      $('.js-askem__container .askem').show();
    } else {
      $('.js-askem__container .js-askem-cookie-compliance').show();
    }

    // Only load once.
    loadReactAndShare = () => {};
  };

  if (Drupal.cookieConsent.initialized()) {
    loadReactAndShare();
  } else {
    Drupal.cookieConsent.loadFunction(loadReactAndShare);
  }
})(jQuery, Drupal, drupalSettings);
