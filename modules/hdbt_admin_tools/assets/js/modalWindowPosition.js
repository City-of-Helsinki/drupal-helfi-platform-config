// Fix for modal window position, when advanced details element is opened.
// See: https://www.drupal.org/project/editor_advanced_link.

(function (Drupal, $, once) {

  'use strict';

  Drupal.behaviors.modal_window_position = {
    attach: function () {
      const modalWindowPosition = once(
        'modal_window_position',
        '.editor-link-dialog details[data-drupal-selector="edit-advanced"]'
      );

      // Reset modal window position when advanced details element is opened or
      // closed to prevent the element content to be out of the screen.
      if (modalWindowPosition.length > 0) {
        $(modalWindowPosition)
          .on('toggle', function () {
            $('#drupal-modal').dialog({
              position: {
                of: window
              }
            });
          });
      }
    }
  };

}(Drupal, jQuery, once));
