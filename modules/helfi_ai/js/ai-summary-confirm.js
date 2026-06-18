/**
 * @file
 * Confirms before regenerating an existing AI summary.
 *
 * The Generate button is a Drupal AJAX button. Drupal binds its AJAX handler in
 * the bubbling phase, so this listener is registered in the capture phase: it
 * runs first and can cancel the AJAX request (preventDefault +
 * stopImmediatePropagation) if the editor declines, leaving the existing
 * summary untouched.
 */
((Drupal, once) => {
  Drupal.behaviors.helfiAiSummaryConfirm = {
    attach(context) {
      once('helfi-ai-summary-confirm', '[data-helfi-ai-confirm]', context).forEach(
        (button) => {
          button.addEventListener(
            'click',
            (event) => {
              const message = button.getAttribute('data-helfi-ai-confirm');
              // eslint-disable-next-line no-alert
              if (message && !window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();
              }
            },
            true,
          );
        },
      );
    },
  };
})(Drupal, once);
