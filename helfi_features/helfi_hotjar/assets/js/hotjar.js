/**
 * @file
 * Loads Hotjar script.
 *
 * Requires EU Cookie Compliance module.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  var loadHotjar = function () {
    if (!drupalSettings.hotjar.id || !drupalSettings.hotjar.version) {
      return;
    }

    if (typeof Drupal.eu_cookie_compliance === 'undefined') {
      return;
    }

    if (!Drupal.eu_cookie_compliance.hasAgreed('statistics')) {
      return;
    }

    // Hotjar tracking code.
    (function (h, o, t, j, a, r) {
      h.hj = h.hj || function () {
        (h.hj.q = h.hj.q || []).push(arguments)
      };
      h._hjSettings = {hjid: drupalSettings.hotjar.id, hjsv: drupalSettings.hotjar.version};
      a = o.getElementsByTagName('head')[0];
      r = o.createElement('script');
      r.async = 1;
      r.src = t + h._hjSettings.hjid + j + h._hjSettings.hjsv;
      a.appendChild(r);
    })(window, document, 'https://static.hotjar.com/c/hotjar-', '.js?sv=');

  };

  // Run after page is ready.
  $(document).ready(function () {
    loadHotjar();
  });
})(jQuery, Drupal, drupalSettings);
