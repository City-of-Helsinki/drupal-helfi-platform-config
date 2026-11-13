/**
 * Outdated browser notification.
 *
 * Shows the notification rendered by the "outdated_browser" block when the
 * user's browser is recognised and older than the supported versions. See
 * tools/build-outdated-browser.mjs for the supported browser versions.
 *
 * IMPORTANT: This script must run on as old browsers as possible so:
 * - It is written in ES5
 * - It has no dependencies to Drupal core JS or hdbt scripts,
 *   which might not run on old browsers.
 * - It is not bundled with esbuild (the bundle wrapper uses arrow functions)
 * - It is not formatted with Biome (it adds trailing commas to function calls).
 * - tools/build-outdated-browser.mjs injects the user agent regexes in place
 *   of OUTDATED_BROWSER_REGEXES and writes the result to dist/js.
 * - The library is not aggregated (preprocess: false).
 *
 * Add "?outdated-browser" to the URL to show the notification on any browser
 * for testing purposes. Dismissing is ignored when using the testing parameter.
 */
(function (regexes) {
  'use strict';

  var STORAGE_KEY = 'helfi-settings';
  var HIDDEN_KEY = 'hidden-helfi-announcements';
  var ID_PREFIX = 'outdated-browser:';
  var dismissId = ID_PREFIX + navigator.userAgent;
  var isTest = /[?&]outdated-browser(=|&|$)/.test(window.location.search);

  function isOutdated() {
    var userAgent = navigator.userAgent;
    return regexes.known.test(userAgent) && !regexes.supported.test(userAgent);
  }

  function loadSettings() {
    try {
      var data = window.localStorage.getItem(STORAGE_KEY);
      var settings = data ? JSON.parse(data) : {};
      return settings && typeof settings === 'object' ? settings : {};
    } catch (e) {
      // Local storage is not available for example in private mode.
      return {};
    }
  }

  // Check if the notification is closed by the user.
  function isDismissed() {
    var hidden = loadSettings()[HIDDEN_KEY];
    if (!Array.isArray(hidden)) {
      return false;
    }
    for (var i = 0; i < hidden.length; i++) {
      if (hidden[i] === dismissId) {
        return true;
      }
    }
    return false;
  }

  function saveDismissed() {
    var settings = loadSettings();
    var hidden = Array.isArray(settings[HIDDEN_KEY]) ? settings[HIDDEN_KEY] : [];
    var values = [];

    // Remove the ids of the previous browser versions.
    for (var i = 0; i < hidden.length; i++) {
      if (typeof hidden[i] !== 'string' || hidden[i].indexOf(ID_PREFIX) !== 0) {
        values.push(hidden[i]);
      }
    }
    values.push(dismissId);
    settings[HIDDEN_KEY] = values;

    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    } catch (e) {
      // Local storage is not available, the notification is closed only for
      // the current page load.
      return;
    }

    try {
      // Notify hdbt LocalStorageManager instances so that they reload the data
      // instead of overwriting it with their stale copy.
      window.dispatchEvent(new CustomEvent('localstorage-save-event'));
    } catch (e) {
      // CustomEvent constructor or hdbt scripts are not supported.
    }
  }

  function moveFocus() {
    // This notification is the first item in the "before content" region.
    // Move the focus to the last focusable item before it (breadcrumb or the
    // navigation toggle buttons), similarly to closableAnnouncements.js in hdbt.
    var selectors = ['.breadcrumb a:last-of-type', '.nav-toggle__button'];

    for (var i = 0; i < selectors.length; i++) {
      var elements = document.querySelectorAll(selectors[i]);
      for (var j = elements.length - 1; j >= 0; j--) {
        if (elements[j].offsetParent !== null) {
          elements[j].focus();
          return;
        }
      }
    }
  }

  function removeAnnouncementElement(element) {
    if (element.parentNode) {
      element.parentNode.removeChild(element);
    }
  }

  function close(wrapper) {
    var announcement = wrapper.querySelector('.announcement');
    saveDismissed();

    // Set a fixed height to allow the CSS animation (see hdbt _announcement.scss).
    if (announcement.style.setProperty) {
      announcement.style.setProperty('--js-announcement-height', announcement.offsetHeight + 'px');
    }

    window.setTimeout(function () {
      if (announcement.classList) {
        announcement.classList.add('js-announcement--hide');
      }
      moveFocus();
      window.setTimeout(function () {
        removeAnnouncementElement(wrapper);
      }, 500);
    }, 1);
  }

  function init() {
    var wrappers = document.querySelectorAll('.js-outdated-browser');
    var show = isTest || (isOutdated() && !isDismissed());

    for (var i = 0; i < wrappers.length; i++) {
      var wrapper = wrappers[i];

      if (!show) {
        // Remove the hidden markup so that it is not picked up by the
        // announcement scripts for example when moving the focus.
        removeAnnouncementElement(wrapper);
        continue;
      }

      var button = wrapper.querySelector('.announcement__close');
      if (button) {
        button.addEventListener('click', close.bind(null, wrapper));
      }
      wrapper.removeAttribute('hidden');
      // Browsers without support for the hidden attribute.
      wrapper.style.display = 'block';
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(OUTDATED_BROWSER_REGEXES);
