(function (Drupal, drupalSettings) {
  Drupal.behaviors.hdbt_cookie_banner = {
    attach: function () {
      if (
        typeof window.hds !== 'undefined' &&
        typeof window.hds.CookieConsentCore !== 'undefined'
      ) {
        const apiUrl = drupalSettings.hdbt_cookie_banner.apiUrl;
        const options = {
          language: drupalSettings.hdbt_cookie_banner.langcode,
          theme: drupalSettings.hdbt_cookie_banner.theme,
          settingsPageSelector: drupalSettings.hdbt_cookie_banner.settingsPageSelector,
          spacerParentSelector: '.footer',
        };

        window.hds.CookieConsentCore.create(apiUrl, options);
      }
    }
  }
})(Drupal, drupalSettings);
