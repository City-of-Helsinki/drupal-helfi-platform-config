((Drupal, once) => {
  /**
   * Switches between the similar content tabs.
   */
  Drupal.behaviors.helfiSearchSimilarContent = {
    attach(context) {
      once('similar-content', '[data-similar-content-tab]', context).forEach((tab) => {
        const root = tab.closest('[data-similar-content]');
        if (!root) {
          return;
        }

        tab.addEventListener('click', () => {
          const index = tab.dataset.similarContentTab;

          root.querySelectorAll('[data-similar-content-tab]').forEach((other) => {
            other.setAttribute('aria-selected', String(other === tab));
          });
          root.querySelectorAll('[data-similar-content-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.similarContentPanel !== index;
          });
        });
      });
    },
  };
})(Drupal, once);
