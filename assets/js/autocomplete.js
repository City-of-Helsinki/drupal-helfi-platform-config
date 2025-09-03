const LOCATION_OPTION = Drupal.t('Use current Location', {}, { context: 'Helsinki near you' });
const API_URL = 'https://api.hel.fi/servicemap/v2/address/';
const LOCATION_LOADING = 'location-loading';

const { currentLanguage } = drupalSettings.path;

const locationOptionLabel = `
  <div>
    <span class="hel-icon hel-icon--locate" role="img" aria-hidden="true"></span>
    ${LOCATION_OPTION}
  </div>
`;

let abortController = new AbortController();

/**
 * Get the most appropriate translation for address.
 * 
 * @param {object} fullName - Translations object
 * @return {string} - the result
 */
const getTranslation = (fullName) => {
  if (fullName[currentLanguage]) {
    return fullName[currentLanguage];
  }

  if (fullName.fi) {
    return fullName.fi;
  }

  return Object.values(fullName)[0];
};

((Drupal, once) => {
  /**
   * Initialize autocomplete.
   *
   * @param {HTMLSelectElement} element Select element.
   */
  const init = element => {
    // eslint-disable-next-line no-undef
    if (!A11yAutocomplete) {
      throw new Error('A11yAutocomplete object not found. Make sure the library is loaded.');
    }

    if (!drupalSettings.helsinki_near_you_form) {
      throw new Error('Helsinki near you form object not found. Configuration cannot be loaded for autocomplete.');
    }

    // Dont add 'Use current location' option if location not available
    let defaultOptions = 'geolocation' in navigator ? [{
      label: locationOptionLabel,
      value: LOCATION_OPTION,
      index: 0,
      item: {
        label: LOCATION_OPTION,
        value: LOCATION_OPTION,
      }
    }] : [];

    const parent = element.closest('.hds-text-input');

    /**
     * Renders automatic location error.
     */
    const displayLocationError = () => {
      parent.classList.add('hds-text-input--invalid');
      const errorSpan = document.createElement('span');
      errorSpan.classList.add('hds-text-input__error-text');
      errorSpan.textContent = Drupal.t('We couldn\'t retrieve your current location. Try entering an address.', {}, { context: 'Helsinki near you' });
      parent.appendChild(errorSpan);

      // Remove automatic location from default options
      defaultOptions = [];
    };

    /**
     * Removes automatic location error.
     */
    const removeLocationError = () => {
      parent.classList.remove('hds-text-input--invalid');
      parent.querySelector('.hds-text-input__error-text')?.remove();
    };

    const {
      autocompleteRoute,
      noResultsAssistiveHint,
      someResultsAssistiveHint,
      oneResultAssistiveHint,
      highlightedAssistiveHint,
      inputAssistiveHint,
      minCharAssistiveHint
    } = drupalSettings.helsinki_near_you_form;

    // eslint-disable-next-line no-undef
    const autocomplete = A11yAutocomplete(element, {
      classes: {
        inputLoading: 'loading',
        wrapper: 'helfi-etusivu-autocomplete',
      },
      highlightedAssistiveHint,
      inputAssistiveHint,
      minCharAssistiveHint,
      minChars: 0,
      noResultsAssistiveHint,
      oneResultAssistiveHint,
      someResultsAssistiveHint,
      source: async(searchTerm, results) => {
        if (searchTerm.length < 3) {
          return results(defaultOptions);
        }

        try {
          abortController.abort();
          abortController = new AbortController();

          const response = await fetch(`${autocompleteRoute}?q=${searchTerm}`, {
            signal: abortController.signal,
          });

          const data = await response.json();
          results(defaultOptions.concat(data));
        }
        catch (e) {
          if (e.name === 'AbortError') {
            return;
          }

          // eslint-disable-next-line no-console
          console.error(e);
          results(defaultOptions);
        }
      },
    });
    const autocompleteInstance = autocomplete._internal_object;

    /**
     * Reflect loading and filling location in UI.
     *
     * @param {boolean} state - true to set loading.
     */
    const setLoading = (state) => {
      autocompleteInstance.close();

      if (state) {
        element.classList.toggle(LOCATION_LOADING, true);
        return;
      }

      element.classList.toggle(LOCATION_LOADING, false);
    };

    // Handle automatic location selection
    element.addEventListener('autocomplete-select', (event) => {
      if (event.detail.selected.value !== LOCATION_OPTION) {
        return;
      }

      event.preventDefault();
      setLoading(element, autocompleteInstance, true);
      navigator.geolocation.getCurrentPosition(async(position) => {
        const { coords: { latitude, longitude } } = position;
        
        const params = new URLSearchParams({
          lat: latitude,
          lon: longitude,
        });
        const reqUrl = new URL(API_URL);
        reqUrl.search = params.toString();

        try {
          const response = await fetch(reqUrl.toString());
          const json = await response.json();
          event.target.value = getTranslation(json.results[0].full_name);
        }
        catch(e) {
          displayLocationError();
        }
        finally {
          setLoading(false);
        }
      }, () => {
        displayLocationError();
        setLoading(false);
      });
    });
    
    // Opens the dropdown on focus when input is empty
    // Not supported by the a11y-autocomplete library
    element.addEventListener('focus', () => {
      if (element.classList.contains(LOCATION_LOADING)) {
        return;
      }

      if (autocompleteInstance.input.value === '' && defaultOptions.length) {
        autocompleteInstance.displayResults(defaultOptions);
      }
    });
    // Similar to above, allow opening list with arrow keys
    element.addEventListener('keydown', (event) => {
      if (
        autocompleteInstance.input.value === '' &&
        defaultOptions.length &&
        autocompleteInstance.suggestions.length === 0 &&
        event.key === 'ArrowDown'
      ) {
        autocompleteInstance.displayResults(defaultOptions);
      }
    });
    // Hide location error input when changing input
    element.addEventListener('change', removeLocationError);
  };

  Drupal.behaviors.helfi_etusivu_autocomplete = {
    attach(context) {
      once(
        'a11y_autocomplete_element',
        '[data-helfi-etusivu-autocomplete]',
        context,
      ).forEach(init);
    },
  };
})(Drupal, once);
