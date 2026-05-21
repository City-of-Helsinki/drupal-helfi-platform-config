(($, Drupal, drupalSettings) => {
  let scriptLoaded = false;

  const loadScript = () => {
    if (scriptLoaded) return;
    scriptLoaded = true;

    window.askem = { settings: { apiKey: drupalSettings.reactAndShareApiKey, disableFonts: true } };

    if (drupalSettings.siteName !== undefined) {
      window.askem.settings.categories = [drupalSettings.siteName];
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
  };

  const loadReactAndShare = () => {
    if (Drupal.cookieConsent.getConsentStatus(['statistics'])) {
      loadScript();
      $('.js-askem__container .js-askem-cookie-compliance').hide();
      $('.js-askem__container .askem').show();
    } else {
      $('.js-askem__container .askem').hide();
      $('.js-askem__container .js-askem-cookie-compliance').show();
    }
  };

  if (Drupal.cookieConsent.initialized()) {
    loadReactAndShare();
  } else {
    Drupal.cookieConsent.loadFunction(loadReactAndShare);
  }

  // Re-run the loadReactAndShare when cookie consent changes.
  window.addEventListener('hds-cookie-consent-changed', loadReactAndShare);
})(jQuery, Drupal, drupalSettings);
