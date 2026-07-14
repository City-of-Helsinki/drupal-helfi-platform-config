/**
 * @file
 * Applies a chosen AI title suggestion to the node title field.
 */
((Drupal, once) => {
  /**
   * Close the modal.
   */
  const closeModal = () => {
    if (window.jQuery) {
      window.jQuery('#drupal-modal').dialog('close');
    }
  };

  Drupal.behaviors.helfiAiTitleSuggest = {
    attach(context) {
      once('ai-title-reorder', '.ai-title', context).forEach((field) => {
        const input = field.querySelector('input[name="title[0][value]"]');
        const suggest = field.querySelector('.ai-suggest');
        if (input && suggest) {
          input.after(suggest);
        }
      });

      once('ai-suggestions-apply', '.ai-suggestions__apply', context).forEach((button) => {
        button.addEventListener('click', () => {
          const selected = document.querySelector('input[name="helfi_ai_title"]:checked');
          const input = document.querySelector('input[name="title[0][value]"]');
          if (selected && input) {
            input.value = selected.value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
          }
          closeModal();
        });
      });

      once('ai-suggestions-cancel', '.ai-suggestions__cancel', context).forEach((button) => {
        button.addEventListener('click', closeModal);
      });
    },
  };
})(Drupal, once);
