/**
 * @file
 * Select2 Icon integration.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.select2IconIntegration = {
    attach: function (context) {
      $('.select2-icon', context).on('select2-init', function (event) {
        let config = $(event.target).data('select2-config');

        const templateHandler = function (parentHandler) {
          return function (option, item) {
            if (parentHandler) { parentHandler(option, item); }
            if (!option.id) { return option.text; }
            if (item) {
              let iconName = $(option.element).attr('data-select2-icon');
              if (iconName) {
                return $(`
                  <span style="align-items: center; display: flex; height: 100%;">
                    <span class="hel-icon hel-icon--${iconName}" aria-hidden="true"></span>
                    <span style="margin-left: 8px;">${option.text}</span>
                  </span>
                `);
              }
            }
          };
        };

        config.templateSelection = templateHandler(config.templateSelection);
        config.templateResult = templateHandler(config.templateResult);
        $(event.target).data('select2-config', config);
      });

      // The Select2 integration initialization library is overridden in
      // HDBT Admin theme because of a race condition when rendering Select2
      // Icon widget in an iframe (dialog). See. hdbt_admin.info.yml:20.
      //
      // How to reproduce race condition:
      // - Remove select2 library-override from hdbt_admin.info.yml:20
      // - Clear the caches and try to render a widget extending select2-widget
      //   in an iframe.
      //
      // The following Select2 integration widget initialization is copied
      // from Select2 module (select2/js/select2.js).
      // This will prevent the race condition when the select2 widget
      // initialization is supposed to be run before any custom widget
      // reacting to "select2-init" event.
      $('.select2-widget', context).once('select2-init').each(function () {
        var config = $(this).data('select2-config');
        config.createTag = function (params) {
          var term = $.trim(params.term);
          if (term === '') {
            return null;
          }
          return {
            id: '$ID:' + term,
            text: term
          };
        };
        config.templateSelection = function (option, container) {
          // The placeholder doesn't have value.
          if ('element' in option && 'value' in option.element) {
            // Add option value to selection container for sorting.
            $(container).data('optionValue', option.element.value);
          }
          return option.text;
        };
        if (Object.prototype.hasOwnProperty.call(config, 'ajax')) {
          config.ajax.data = function (params) {
            var selected = [];
            if (Array.isArray($(this).val())) {
              selected = $(this).val();
            }
            else if ($(this).val() !== '') {
              selected = [$(this).val()];
            }
            return $.extend({}, params, {
              q: params.term,
              selected: selected.filter(function (selected) {
                return !selected.startsWith('$ID:');
              })
            });
          };
        }
        $(this).data('select2-config', config);

        // Emit an event, that other modules have the chance to modify the
        // select2 settings. Make sure that other JavaScript code that rely on
        // this event will be loaded first.
        // @see: modules/select2_publish/select2_publish.libraries.yml
        $(this).trigger('select2-init');
        config = $(this).data('select2-config');

        // If config has a dropdownParent property, wrap it a jQuery object.
        if (Object.prototype.hasOwnProperty.call(config, 'dropdownParent')) {
          config.dropdownParent = $(config.dropdownParent);
        }
        $(this).select2(config);

        // Copied from https://github.com/woocommerce/woocommerce/blob/master/assets/js/admin/wc-enhanced-select.js#L118
        if (Object.prototype.hasOwnProperty.call(config, 'ajax') && config.multiple) {
          var $select = $(this);
          var $list = $select.next('.select2-container').find('ul.select2-selection__rendered');
          Sortable.create($list[0], {
            draggable: 'li:not(.select2-search)',
            forceFallback: true,
            onEnd: function () {
              $($list.find('.select2-selection__choice').get().reverse()).each(function () {
                $select.prepend($select.find('option[value="' + $(this).data('optionValue') + '"]').first());
              });
            }
          });
        }
      });

    }
  };

})(jQuery, Drupal, drupalSettings);
