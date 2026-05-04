/**
 * @file Helfi select element.
 *
 * See helfi_select FormElement plugin.
 */
((Drupal, once, TomSelect) => {
  function renderOption(item, escapeFn) {
    return `
      <div>
        <span>
          ${escapeFn(item.text)}
        </span>

        <span class="hel-icon hel-icon--check" role="img" aria-hidden="true"></span>
      </div>
    `;
  }

  function normalizeAutocompleteResults(data) {
    const items = Array.isArray(data) ? data : data?.results || [];
    return items.map((item) => ({
      value: String(item.value ?? item.id ?? ''),
      text: item.label ?? item.text ?? '',
    }));
  }

  function buildAutocompleteConfig(autocompletePath) {
    return {
      valueField: 'value',
      labelField: 'text',
      searchField: ['text'],
      shouldLoad: (query) => query.length > 2,
      load: (query, callback) => {
        const url = new URL(autocompletePath, window.location.origin);
        url.searchParams.set('q', query);
        fetch(url)
          .then((response) => response.json())
          .then((data) => callback(normalizeAutocompleteResults(data)))
          .catch(() => callback());
      },
    };
  }

  function initSelect(element) {
    const hasEmptyOption = Array.from(element.options).some((option) => option.value === '');
    const autocompletePath = element.dataset.autocompletePath;

    const baseConfig = {
      allowEmptyOption: hasEmptyOption,
      controlInput: null,
      openOnFocus: false,
      placeholder: element.getAttribute('placeholder') || undefined,
      render: {
        option: (item, escapeFn) => renderOption(item, escapeFn),
        loading: () => {
          return `<div class="spinner"></div>`;
        },
      },
    };

    const plugins = {};

    if (element.multiple) {
      plugins.checkbox_options = {};
      plugins.clear_button = {};
    }
    if (autocompletePath) {
      plugins.dropdown_input = {};
    }

    const autocompleteConfig = autocompletePath ? buildAutocompleteConfig(autocompletePath) : {};

    const ts = new TomSelect(element, { ...baseConfig, plugins, ...autocompleteConfig });

    let wasOpenOnMouseDown = false;

    ts.control.addEventListener('mousedown', () => {
      wasOpenOnMouseDown = ts.isOpen;
    });

    ts.control.addEventListener('click', (e) => {
      if (e.detail > 0 && !wasOpenOnMouseDown && !e.target.closest('.item')) {
        ts.open();
      }
    });

    let wasOpenOnKeydown = false;

    // Capture phase: runs before tom-select's bubble handler so we can snapshot isOpen.
    ts.control.addEventListener('keydown', (e) => {
      if (e.key === ' ' || e.key === 'Enter') {
        wasOpenOnKeydown = ts.isOpen;
      }
    }, true);

    ts.control.addEventListener('keydown', (e) => {
      if (e.key !== ' ' && e.key !== 'Enter') return;
      if (!wasOpenOnKeydown) {
        e.preventDefault();
        ts.open();
      } else if (!autocompletePath && ts.activeOption && ts.isOpen) {
        // Space: tom-select has no handler so dropdown is still open — click the option.
        e.preventDefault();
        ts.activeOption.click();
      }
      // Enter: tom-select already handled select+close, wasOpenOnKeydown=true but isOpen=false — do nothing.
    });

    return ts;
  }

  Drupal.behaviors.helfiSelect = {
    attach: (context) => {
      once('helfi-select', 'select.helfi-select', context).forEach(initSelect);
    },
  };
})(Drupal, once, TomSelect);
