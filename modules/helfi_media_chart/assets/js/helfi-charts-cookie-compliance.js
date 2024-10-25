/**
 * @file
 * Load chart once the user has approved the required cookie category.
 */
(function ($, Drupal) {
  'use strict';

  let loadHelfiCharts = () => {
    if (Drupal.cookieConsent.getConsentStatus(['statistics'])) {
      const chartContentElements = document.querySelectorAll('.helfi-charts-content');

      // Populate all chart content elements with iframes on page
      for (let i = 0; i < chartContentElements.length; ++i) {
        if (chartContentElements[i].dataset && chartContentElements[i].dataset.src && chartContentElements[i].dataset.title) {
          const iframeElement = document.createElement('iframe');
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

  if (Drupal.cookieConsent.initialized()) {
    loadHelfiCharts();
  } else {
    Drupal.cookieConsent.loadFunction(loadHelfiCharts);
  }
})(jQuery, Drupal);
