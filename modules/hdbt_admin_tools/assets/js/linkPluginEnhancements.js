// Handle protocol selection for the link dialog.

(function (Drupal, $) {

  'use strict';

  // Simple show / hide functionality.
  const handleVisibility = (element, show = true) => (
    (show) ? element.show(): element.hide()
  );

  Drupal.behaviors.linkProtocolSelection = {
    attach: function () {
      const hrefInput = $('form.editor-link-dialog input[data-drupal-selector="edit-attributes-href"]');
      const protocol = $('form.editor-link-dialog select[data-drupal-selector="edit-attributes-data-protocol"]');
      const protocolSelect = $('form.editor-link-dialog .form-item--attributes-data-protocol');

      if (hrefInput && protocol) {

        // Get list of options to be used later.
        const options = $.map(protocol.children('option'), (option) => option.value);

        // Reset protocol select.
        if (hrefInput.val()) {
          handleVisibility(protocolSelect, false);
        }

        // Handle protocol select visibility based on user input.
        hrefInput.on('input', (event) => {
          const input = $(event.target);
          handleVisibility(protocolSelect,input.val() === '');
        });

        // Change input value based on protocol selection.
        protocol.change((event) => {
          const chosenProtocol = $(event.target).val();
          if (
            chosenProtocol &&
            chosenProtocol !== 'false' &&
            (hrefInput.val() === '' || options.includes(hrefInput.val()))
          ) {
            hrefInput.val(chosenProtocol);
            hrefInput.focus();
          }
        }).change();

        // If user has selected text before the link exists, apply the
        // selected text from global variable to current link text input field.
        const textInput = $('form.editor-link-dialog input[data-drupal-selector="edit-attributes-data-link-text"]');
        if (!textInput.val() && window.drupalLinkTextSelection !== undefined){
          textInput.val(window.drupalLinkTextSelection);
        }
      }
    }
  };

  Drupal.behaviors.linkIconSelection = {
    attach () {
      const design = $('form.editor-link-dialog select[data-drupal-selector="edit-attributes-data-design"]');

      if (design) {
        design.change((event) => {
          const chosenDesign = $(event.target).val();
          handleVisibility($('form.editor-link-dialog .form-item--attributes-data-selected-icon'), chosenDesign !== 'link');
        }).change();
      }
    }
  };

}(Drupal, jQuery));
