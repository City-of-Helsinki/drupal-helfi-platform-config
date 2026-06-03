((Drupal, drupalSettings) => {
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

  const toggle = (selector, visible) => {
    document.querySelectorAll(selector).forEach((element) => {
      element.style.display = visible ? '' : 'none';
    });
  };

  const loadReactAndShare = () => {
    const askemBanner = '.js-askem__container .askem:not(.js-askem-cookie-compliance)';
    const complianceBanner = '.js-askem__container .js-askem-cookie-compliance';

    if (Drupal.cookieConsent.getConsentStatus(['statistics'])) {
      loadScript();
      toggle(complianceBanner, false);
      toggle(askemBanner, true);
    } else {
      toggle(askemBanner, false);
      toggle(complianceBanner, true);
    }
  };

  if (Drupal.cookieConsent.initialized()) {
    loadReactAndShare();
  } else {
    Drupal.cookieConsent.loadFunction(loadReactAndShare);
  }

  // Re-run the loadReactAndShare when cookie consent changes.
  window.addEventListener('hds-cookie-consent-changed', loadReactAndShare);
})(Drupal, drupalSettings);
