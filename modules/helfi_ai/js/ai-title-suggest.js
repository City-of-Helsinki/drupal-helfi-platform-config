/**
 * @file
 * Applies a chosen AI title suggestion to the node title field.
 *
 * The suggestions are rendered in a modal dialog by TitleSuggestionFormAlter as
 * a radio option box with Apply / Cancel actions. Apply reads the selected
 * radio, writes its value into the title input and closes the dialog; Cancel
 * just closes it.
 */
((Drupal, once) => {
  const closeModal = () => {
    // Drupal's modal is a jQuery UI dialog on #drupal-modal.
    if (window.jQuery) {
      window.jQuery('#drupal-modal').dialog('close');
    }
  };

  Drupal.behaviors.helfiAiTitleSuggest = {
    attach(context) {
      once('helfi-ai-title-reorder', '.helfi-ai-title', context).forEach(
        (field) => {
          const input = field.querySelector('input[name="title[0][value]"]');
          const suggest = field.querySelector('.helfi-ai-suggest');
          if (input && suggest) {
            input.after(suggest);
          }
        },
      );

      once('helfi-ai-suggestions-apply', '.helfi-ai-suggestions__apply', context).forEach(
        (button) => {
          button.addEventListener('click', () => {
            const selected = document.querySelector(
              'input[name="helfi_ai_title"]:checked',
            );
            const input = document.querySelector(
              'input[name="title[0][value]"]',
            );
            if (selected && input) {
              input.value = selected.value;
              // Let Drupal/other widgets react as if typed.
              input.dispatchEvent(new Event('input', { bubbles: true }));
              input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            closeModal();
          });
        },
      );

      once('helfi-ai-suggestions-cancel', '.helfi-ai-suggestions__cancel', context).forEach(
        (button) => {
          button.addEventListener('click', closeModal);
        },
      );
    },
  };
})(Drupal, once);
