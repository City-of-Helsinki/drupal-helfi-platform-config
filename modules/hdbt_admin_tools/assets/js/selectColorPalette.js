/**
 * @file Select color palette.
 */
(function (Drupal, once) {
  'use strict';

  function renderTemplate(item, escape) {
    return `
      <div class="color-selection-wrapper hdbt-theme--${item.value}">
        <div class="selection">${escape(item.text)}</div>
        <div class="colors">
          <div class="color-selection color-selection--primary"></div>
          <div class="color-selection color-selection--secondary"></div>
          <div class="color-selection color-selection--accent"></div>
        </div>
      </div>
    `;
  }

  Drupal.behaviors.selectColorPalette = {
    attach: function (context) {
      const elements = once('select-color-palette', 'select.select-color-palette', context);

      elements.forEach((element)=> {
        const settings = {
          allowEmptyOption: false,
          controlInput: null,
          render: {
            option: (item, escape) => (
              renderTemplate(item, escape)
            ),
            item: (item, escape) => (
              renderTemplate(item, escape)
            ),
          }
        };
        const tomSelect = new TomSelect(element, settings);
      });
    }
  };

})(Drupal, once);
