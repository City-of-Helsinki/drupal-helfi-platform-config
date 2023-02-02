/**
 * @file Design selection.
 */
(function ($, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.designSelection = {
    attach: function (context) {

      /**
       * Perform alterations for each "design select" select element, when
       * select2 initialization has been triggered.
       */
      $('.design-selection', context).on('select2-init', function (event) {
        let config = $(event.target).data('select2-config');
        let designSelect = $(event.target).data('designSelect');

        /**
         * TemplateHandler handles each select item in select2 list.
         */
        const templateHandler = function (parentHandler, design) {
          const parentDesign = design;
          return function (option, container) {
            if (parentHandler) { parentHandler(option, container); }
            if (!option.id) { return option.text; }
            if (!parentDesign) { return option.text; }

            // Craft path to thumbnails based on item values and base design.
            const image = (option.id in drupalSettings.designSelect.images)
              ? drupalSettings.designSelect.images[option.id] : '';

            // Craft the image template.
            return $(`
              <div class="design-selection__wrapper">
                <span>${option.text}</span>
                <img src="${image}" data-hover-title="${option.text}" data-hover-image="${image}" class="design-selection__thumbnail" />
              </div>
            `);
          };
        };

        // Configuration overrides for the design select tool.
        config.templateResult = templateHandler(config.templateResult, designSelect);
        config.minimumResultsForSearch = -1;
        config.theme = 'default design-selection';
        $(event.target).data('select2-config', config);
      });

      /**
       * Assign image preview to selection thumbnail.
       */
      const selector = '.select2-container--open .design-selection__thumbnail';
      $(selector, context).imagePreviewer(selector);
    }
  };

})(jQuery, Drupal, drupalSettings);
