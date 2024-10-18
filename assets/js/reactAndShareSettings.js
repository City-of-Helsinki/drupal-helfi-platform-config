(function ($, Drupal) {
  'use strict';

  var loadReactAndShare = function () {
    if (window && window.hds.cookieConsent && window.hds.cookieConsent.getConsentStatus(['statistics'])) {
      window.rnsData = {
        apiKey: drupalSettings.reactAndShareApiKey,
        disableFa: true,
        disableFonts: true,
      };

      if (drupalSettings.siteName !== undefined) {
        window.rnsData.categories = [drupalSettings.siteName]
      }

      var scriptElement = document.createElement('script');
      scriptElement.async = true;
      scriptElement.src = 'https://cdn.reactandshare.com/plugin/rns.js';

      document.body.appendChild(scriptElement);

      $('.js-react-and-share__container .js-react-and-share-cookie-compliance').hide();
      $('.js-react-and-share__container .rns').show();
    }
    else {
      $('.js-react-and-share__container .js-react-and-share-cookie-compliance').show();
    }

    // Only load once.
    loadReactAndShare = function () {};
  };

  if (window.hds.cookieConsent) {
    loadReactAndShare();
  } else {
    $(document).on('hds_cookieConsent_ready', loadReactAndShare);
  }
})(jQuery, Drupal);
