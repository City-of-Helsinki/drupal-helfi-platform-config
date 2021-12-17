/**
 * @file
 * Load React & Share widget once the user has approved the "statistics" cookie category.
 *
 * Depends on EU Cookie Compliance module.
 */
 (function ($, Drupal) {
  'use strict';

  var loadReactAndShare = function () {
    if (Drupal.eu_cookie_compliance.hasAgreed('statistics')) {
      $('.js-react-and-share__container .rns').show();
    }
    else {
      $('.js-react-and-share__container .js-react-and-share-cookie-compliance').show();
    }

    // Only load once.
    loadReactAndShare = function () {};
  };

  // Run after choosing cookie settings.
  $(document).on('eu_cookie_compliance.changeStatus', loadReactAndShare);

  // Run after page is ready.
  $(document).ready(function () {
    loadReactAndShare();
  });
})(jQuery, Drupal);
