/**
 * @file Select design.
 */
(function (Drupal, drupalSettings, once) {
  "use strict";

  function renderTemplate(item, escape, element) {
    const selection = item.$option.value ?? item.$option.value;

    // Craft path to thumbnails based on item values and base design.
    const image = (selection in drupalSettings.selectDesign.images)
      ? drupalSettings.selectDesign.images[selection] : '';

    return `
      <div class="select-design__wrapper">
        <span>${escape(item.text)}</span>
        <img src="${image}" data-hover-title="${item.text}" data-hover-image="${image}" class="select-design__thumbnail" />
      </div>
    `;
  }

  Drupal.behaviors.selectDesign = {
    attach: function (context) {
      const elements = once('select-design', 'select.select-design', context);

      elements.forEach((element)=>{
        const eventHandler = function(action) {
          return function() {
            imagePreviewer('.select-design .select-design__thumbnail', {}, action);
          };
        };

        const settings = {
          allowEmptyOption: false,
          controlInput: null,
          render: {
            option: function(item, escape) {
              return renderTemplate(item, escape, element);
            },
            item: function(item, escape) {
              return renderTemplate(item, escape, element);
            }
          },
          onDropdownOpen: eventHandler('open'),
          onDropdownClose: eventHandler('close'),
        };
        const tomSelect = new TomSelect(element, settings);
      });

      // Assign image preview to the design selection.
      imagePreviewer('.select-design .select-design__thumbnail');
    }
  };
})(Drupal, drupalSettings, once);
