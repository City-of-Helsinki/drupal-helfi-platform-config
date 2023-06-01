/**
 * @file Select color palette.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.selectColorPalette = {
    attach: function (context) {

      function renderTemplate(item, escape) {
        return `
          <div class="color-selection-wrapper hdbt-theme--${item.id}">
            <div class="selection">${escape(item.text)}</div>
            <div class="colors">
              <div class="color-selection color-selection--primary"></div>
              <div class="color-selection color-selection--secondary"></div>
              <div class="color-selection color-selection--accent"></div>
            </div>
          </div>
        `;
      }

      document.querySelectorAll('.color-palette-selection').forEach((element)=>{
        const settings = {
          plugins: {
            dropdown_input: {},
          },
          allowEmptyOption: false,
          create: false,
          // Custom rendering functions for options and items
          render: {
            option: function(item, escape) {
              return renderTemplate(item, escape);
            },
            item: function(item, escape) {
              return renderTemplate(item, escape);
            }
          }
        };
        new TomSelect(element, settings);
      });

    }
  };

})(Drupal, once);


/**
 * @file Color palette selection.
 */
(function ($, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.colorPaletteSelection = {
    attach: function (context) {

      /**
       * Perform alterations for each "color select" select element, when
       * select2 initialization has been triggered.
       */
      $('.color-palette-selection', context).on('select2-init', function (event) {
        let config = $(event.target).data('select2-config');
        let colorPaletteSelect = $(event.target).data('colorPaletteSelect');

        /**
         * TemplateHandler handles each select item in select2 list.
         */
        const templateHandler = function (parentHandler, colorPalette) {
          const parentColorPalette = colorPalette;
          return function (option, container) {
            if (parentHandler) { parentHandler(option, container); }
            if (!option.id) { return option.text; }
            if (!parentColorPalette) { return option.text; }

            // Craft the image template.
            return $(`
              <div class="color-selection-wrapper hdbt-theme--${option.id}">
                <div class="selection">${option.text}</div>
                <div class="colors">
                  <div class="color-selection color-selection--primary"></div>
                  <div class="color-selection color-selection--secondary"></div>
                  <div class="color-selection color-selection--accent"></div>
                </div>
              </div>
            `);
          };
        };

        // Configuration overrides for the design select tool.
        config.templateSelection = templateHandler(config.templateSelection, colorPaletteSelect);
        config.templateResult = templateHandler(config.templateResult, colorPaletteSelect);
        config.minimumResultsForSearch = -1;
        config.theme = 'default color-palette-selection';
        $(event.target).data('select2-config', config);
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
