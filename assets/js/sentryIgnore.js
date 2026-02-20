/**
 * @file
 * Filter out Sentry errors before they are sent.
 */
((drupalSettings) => {
  // If Raven/Sentry is not enabled, do nothing.
  if (drupalSettings.raven === undefined) {
    return;
  }

  // Preserve any existing beforeSend options.
  const options = drupalSettings.raven.options || {};
  const previousBeforeSend = options.beforeSend;

  /**
   * Safari is more aggressive than other browsers when it comes to network
   * and privacy enforcement, which can cause third-party requests to fail and
   * surface as "TypeError: Load failed".
   *
   * Common causes:
   * - Stricter CORS enforcement
   * - Blocking of third-party endpoints
   * - Blocking redirects across tracking-related domains
   * - Cancelled requests during page navigation
   * - Silent request failures that later surface as "Load failed"
   *
   * This behaviour is typical of Safari/WebKit and does not usually
   * indicate a bug in the application code, but floods the Sentry with these
   * errors.
   */
  const safariLoadFailed = { type: 'TypeError', value: 'Load failed' };

  /**
   * Third-party code sometimes assumes WebCrypto is available and crashes with:
   * "Cannot read properties of undefined (reading 'digest')".
   */
  const webCryptoDigestUndefined = { type: 'TypeError', value: "reading 'digest'" };

  // List of error types and values to ignore.
  const errorMatchers = [
    safariLoadFailed,
    webCryptoDigestUndefined,
    // Add more combinations here if needed:
    // { type: 'TypeError', value: 'Failed to fetch' },
  ];

  /**
   * Checks if the event matches to listed errors.
   *
   * @param {Object} event
   *   The Sentry event.
   *
   * @return {boolean}
   *   TRUE if the event should be dropped.
   */
  const isLoadFailedError = (event) => {
    const exceptions = event?.exception?.values || [];

    return exceptions.some((exception) =>
      errorMatchers.some(
        (matcher) =>
          exception?.type === matcher.type &&
          typeof exception?.value === 'string' &&
          exception.value.includes(matcher.value),
      ),
    );
  };

  /**
   * Custom beforeSend callback.
   *
   * @param event
   * @param hint
   * @returns {*|null}
   */
  drupalSettings.raven.options.beforeSend = (event, hint) => {
    // Do not send errors that match the configured errorMatchers to Sentry.
    if (isLoadFailedError(event)) {
      return null;
    }

    // Delegate to the previous beforeSend callback if one existed.
    if (typeof previousBeforeSend === 'function') {
      return previousBeforeSend(event, hint);
    }

    return event;
  };
})(drupalSettings);
