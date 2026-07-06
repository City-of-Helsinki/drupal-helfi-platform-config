/**
 * @file
 * Confirms before regenerating an existing AI summary.
 */
((Drupal, once) => {
  Drupal.behaviors.helfiAiSummaryConfirm = {
    attach(context) {
      once('helfi-ai-summary-confirm', '[data-helfi-ai-summary-confirm]', context).forEach(
        (button) => {
          button.addEventListener(
            'click',
            (event) => {
              const message = button.getAttribute('data-helfi-ai-summary-confirm');
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
