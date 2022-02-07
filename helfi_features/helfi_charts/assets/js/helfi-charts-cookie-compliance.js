/**
 * @file
 * Load chart once the user has approved the required cookie category.
 */
(function ($, Drupal) {
  'use strict';

  var loadHelfiCharts = function () {
    if (Drupal.eu_cookie_compliance.hasAgreed('statistics')) {
      $('.helfi-charts-content').show();
    }
    else {
      $('.js-helfi-charts-cookie-compliance').show();
    }

    // Only load once.
    loadHelfiCharts = function () {};
  };

  // Run after choosing cookie settings.
  $(document).on('eu_cookie_compliance.changeStatus', loadHelfiCharts);

  // Run after page is ready.
  $(document).ready(function () {
    loadHelfiCharts();
  });
})(jQuery, Drupal);
