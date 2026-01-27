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

  // List of errors to ignore.
  const errorMatchers = [
    { type: 'TypeError', value: 'Load failed' },
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
      errorMatchers.some((matcher) => exception?.type === matcher.type && exception?.value === matcher.value),
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
    // Do not send "Load failed" errors to Sentry.
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
