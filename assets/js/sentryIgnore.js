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
   * Chrome/Edge/Firefox typically throw "TypeError: Failed to fetch".
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
  const failedToFetch = { type: 'TypeError', value: 'Failed to fetch' };

  /**
   * Third-party code sometimes assumes WebCrypto is available and crashes with:
   * "Cannot read properties of undefined (reading 'digest')".
   */
  const webCryptoDigestUndefined = { type: 'TypeError', value: "reading 'digest'" };

  /**
   * Some third-party scripts are designed to run inside hybrid mobile
   * applications (f.e. snapchat), where a native bridge object is injected
   * into the global scope via WKWebView or Android WebView.
   *
   * When such code attempts to access a bridge object like
   * `SCDynimacBridge` in a normal browser environment where it has not
   * been injected, the browser throws:
   *   "ReferenceError: Can't find variable: SCDynimacBridge"
   *
   * In standard web browser contexts this typically does not indicate
   * a defect in the application itself, but rather an environmental
   * mismatch.
   */
  const missingMobileBridge = { type: 'ReferenceError', value: "Can't find variable: SCDynimacBridge" };

  /**
   * In some browsers the Web Storage API may be unavailable or disabled.
   * When a script attempts to access localStorage or indexedDB in such
   * contexts, the browser may throw:
   *   "ReferenceError: Can't find variable:"
   */
  const localStorageUnavailable = { type: 'ReferenceError', value: "Can't find variable: localStorage" };
  const indexedDBUnavailable = { type: 'ReferenceError', value: "Can't find variable: indexedDB" };

  /**
   * HeadlessChrome triggers an error with dialog focus-trap.
   */
  const focusTrap = { type: 'Error', value: 'Your focus-trap must have at least one container' };

  // List of error types and values to ignore.
  const errorMatchers = [
    safariLoadFailed,
    failedToFetch,
    webCryptoDigestUndefined,
    missingMobileBridge,
    localStorageUnavailable,
    indexedDBUnavailable,
    focusTrap,
    // Add more combinations here if needed:
    // { type: 'TypeError', value: 'Failed to fetch' },
  ];

  /**
   * Checks if the event is the cookie-consent storage SecurityError.
   *
   * @todo This should be fixed in HDS cookie consent.
   * The storage calls should be guarded with try/catch.
   * The error is thrown when the library tries to read keys from a storage
   * backend (localStorage, sessionStorage, indexedDB, cacheStorage) in an
   * environment where the browser blocks that operation (incognito).
   *
   * @param {Object} event
   *   The Sentry event.
   *
   * @return {boolean}
   *   TRUE if the event should be dropped.
   */
  const isCookieConsentInsecureOperation = (event) => {
    const exceptions = event?.exception?.values || [];

    return exceptions.some((exception) => {
      if (exception?.type !== 'SecurityError') {
        return false;
      }

      if (typeof exception?.value !== 'string' || !exception.value.includes('The operation is insecure')) {
        return false;
      }

      const frames = exception?.stacktrace?.frames || [];
      return frames.some((frame) => {
        const filename = frame?.filename || '';
        return filename.includes('hds-cookie-consent.min.js') || filename.includes('/hdbt_cookie_banner/');
      });
    });
  };

  /**
   * Checks if the event matches to listed errors.
   *
   * @param {Object} event
   *   The Sentry event.
   *
   * @return {boolean}
   *   TRUE if the event should be dropped.
   */
  const isListedError = (event) => {
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
   * Get cookie value.
   *
   * @param {string} name
   *   Cookie name.
   *
   * @return {string|null}
   *   Cookie value or null.
   */
  const getCookie = (name) => {
    const match = document.cookie
      .split('; ')
      .map((row) => row.split('='))
      .find(([key]) => key === name);

    return match ? match[1] : null;
  };

  /**
   * Extract the cookie consent groups from helfi-cookie-consents.
   *
   * @return {Object|null}
   *   The groups object or null.
   */
  const getCookieConsentGroups = () => {
    const cookieValue = getCookie('helfi-cookie-consents');

    if (!cookieValue) {
      return null;
    }

    try {
      const decoded = decodeURIComponent(cookieValue);
      const parsed = JSON.parse(decoded);

      return parsed?.groups || null;
    } catch (_error) {
      return null;
    }
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
    if (isListedError(event) || isCookieConsentInsecureOperation(event)) {
      return null;
    }

    // Add information about accepted cookies.
    event.breadcrumbs = [
      ...(event.breadcrumbs || []),
      {
        category: 'cookie-consent',
        level: 'info',
        data: getCookieConsentGroups() ?? { '': 'No consent given.' },
      },
    ];

    if (typeof previousBeforeSend === 'function') {
      return previousBeforeSend(event, hint);
    }

    return event;
  };
})(drupalSettings);
