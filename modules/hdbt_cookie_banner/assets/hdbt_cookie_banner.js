(function (Drupal, drupalSettings) {
  Drupal.behaviors.hdbt_cookie_banner = {
    attach: function (context, settings) {

      // Todo initialize hdbt cookie banner.
      fetch(drupalSettings.hdbt_cookie_banner.apiUrl)
        .then(response => response.json())
        .then(console.log)
        .catch(console.error)
    }
  }
})(Drupal, drupalSettings);
