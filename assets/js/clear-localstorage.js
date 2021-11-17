/**
 * @file
 * Clears certain localStorage items when cookie category approval is not given.
 *
 * Depends on EU Cookie Compliance module.
 */
(function ($, Drupal) {
  'use strict';

  var clearLocalStorage = function () {
    if (typeof Drupal.eu_cookie_compliance === 'undefined') {
      return;
    }

    if (!Drupal.eu_cookie_compliance.hasAgreed('statistics')) {
      var requireStatistics = [
        'rnsbid',
        'rnsbid_ts',
      ];

      // Find localStorage keys that starts with rns_reaction_ and add those to
      // items that are removed.
      for (var i = 0; i < localStorage.length; i++) {
        if (localStorage.key(i).substring(0,13) === 'rns_reaction_') {
          requireStatistics.push(localStorage.key(i));
        }
      }

      // Remove items that require approving statistics category.
      for (var i = 0; i < requireStatistics.length; i++) {
        localStorage.removeItem(requireStatistics[i]);
      }
    }
  };

  // Run after page is ready.
  $(document).ready(function () {
    clearLocalStorage();
  });
})(jQuery, Drupal);
