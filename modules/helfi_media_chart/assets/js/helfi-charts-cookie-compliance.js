/**
 * @file
 * Load chart once the user has approved the required cookie category.
 */
(function ($, Drupal) {
  'use strict';

  var loadHelfiCharts = function () {
    if (Drupal.eu_cookie_compliance.hasAgreed('statistics')) {
      var chartContentElements = document.querySelectorAll('.helfi-charts-content');

      // Populate all chart content elements with iframes on page
      for (var i = 0; i < chartContentElements.length; ++i) {
        if (chartContentElements[i].dataset && chartContentElements[i].dataset.src && chartContentElements[i].dataset.title) {
          var iframeElement = document.createElement('iframe');
          iframeElement.src = chartContentElements[i].dataset.src;
          iframeElement.title = chartContentElements[i].dataset.title;
          iframeElement.allow = 'fullscreen';
          chartContentElements[i].replaceChildren(iframeElement);
        }
      }
    } else {
      $('.js-helfi-charts-cookie-compliance').show();
    }

    // Only load once.
    loadHelfiCharts = function () {};
  };

  // Run after page is ready.
  $(document).ready(function () {
    loadHelfiCharts();
  });
})(jQuery, Drupal);
