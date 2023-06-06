/**
 * @file
 * Select icons.
 */
(function (Drupal, once) {
  'use strict';

  function renderTemplate(item, escape) {
    return `
      <span style="align-items: center; display: flex; height: 100%;">
        <span class="hel-icon hel-icon--${item.icon}" aria-hidden="true"></span>
        <span class="hel-icon--name" style="margin-left: 8px;">${escape(item.name)}</span>
      </span>
    `;
  }

  Drupal.behaviors.selectIcons = {
    attach: function (context) {

      const elements = once('select-icon', 'select.select-icon', context);

      elements.forEach((element)=>{
        const settings = {
          plugins: {
            dropdown_input: {},
            remove_button: {
              title: 'Remove this item',
            }
          },
          valueField: 'icon',
          labelField: 'name',
          searchField: ['name'],
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
