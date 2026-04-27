/**
 * @file Helfi select element.
 *
 * See helfi_select FormElement plugin.
 */
(function (Drupal, once, TomSelect) {
  'use strict';

  function renderOption(item, escape) {
    return `
      <div>
        ${escape(item.text)}

        <span class="hel-icon hel-icon--check" role="img" aria-hidden="true"></span>
      </div>
    `;
  }

  Drupal.behaviors.helfiSelect = {
    attach: function (context) {
      once('helfi-select', 'select.helfi-select', context)
        .forEach((element)=> {
          const hasEmptyOption = Array.from(element.options).some((option) => option.value === '');

          new TomSelect(element, {
            allowEmptyOption: hasEmptyOption,
            controlInput: null,
            render: {
              option: (item, escape) => (
                renderOption(item, escape)
              ),
            }
          });
        });
    }
  };

})(Drupal, once, TomSelect);
