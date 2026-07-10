/**
 * @file
 * Confirms before regenerating an existing AI summary.
 */
((Drupal, once) => {
  Drupal.behaviors.helfiAiSummaryConfirm = {
    attach(context) {
      once('ai-summary-confirm', '[data-ai-summary-confirm]', context).forEach(
        (button) => {
          button.addEventListener(
            'click',
            (event) => {
              const message = button.getAttribute('data-ai-summary-confirm');
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
