/**
 * @file
 * eu_cookie_compliance_cookie_values.js
 *
 * Get cookie values.
 */
(function ($, Drupal, drupalSettings, cookies, once) {
  'use strict';

  Drupal.behaviors.euCookieComplianceCookieValues = {
    attach: function (context, settings) {
      const elements = once('eu-cookie-compliance-block', 'body');
      elements.forEach(function () {
        var cookieName = drupalSettings.eu_cookie_compliance_cookie_values.cookieName === '' ? 'cookie-agreed' : drupalSettings.eu_cookie_compliance_cookie_values.cookieName;
        var categories = drupalSettings.eu_cookie_compliance_cookie_values.cookieCategories;
        var values = cookies.get(cookieName + '-categories');
        var selectedCategories = undefined;

        if (values) {
          try {
            selectedCategories = JSON.parse(decodeURI(values.replace(/%2C/g,",")));
          }
          catch (e) { }
        }

        // No suitable cookie categories set.
        if (selectedCategories === undefined || selectedCategories === '[]') {
          $('#edit-accept-all', '.eu-cookie-compliance-block-form .buttons').parent().removeClass('hidden');
        }

        var selectionCount = 0;

        // Get required categories and reflect those on the form
        var requiredCategories = [];
        for (var _categoryName in drupalSettings.eu_cookie_compliance.cookie_categories_details) {
          var _category = drupalSettings.eu_cookie_compliance.cookie_categories_details[_categoryName];
          if (_category.checkbox_default_state === 'required' && $.inArray(_category.id, requiredCategories) === -1) {
            $('#edit-categories-' + _category.id.replace("_", "-"), '#edit-categories').prop("checked", true).prop("disabled", true);
            selectionCount++;
          }
        }

        $.each(selectedCategories, function () {
          $('#edit-categories-' + this.replace("_", "-"), '#edit-categories').prop("checked", true);
          selectionCount++;
        });

        if (selectionCount > 0) {
          $('#edit-withdraw', '.eu-cookie-compliance-block-form .buttons').parent().removeClass('hidden');
        }

        if (selectionCount < categories.length) {
          $('#edit-accept-all', '.eu-cookie-compliance-block-form .buttons').parent().removeClass('hidden');
        }
      });
    }
  }
})(jQuery, Drupal, drupalSettings, window.Cookies, once);
