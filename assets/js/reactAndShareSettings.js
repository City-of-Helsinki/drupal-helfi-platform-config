(function() {
  window.rnsData = {
    apiKey: drupalSettings.reactAndShareApiKey
  };
  var s = document.createElement('script');
  s.src = 'https://cdn.reactandshare.com/plugin/rns.js';

  document.body.appendChild(s);
}());
