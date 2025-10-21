import globalTranslations from './_globalTranslations';

const Mustache = require('mustache');

/**
 * Base class for calculators.
 */
class HelfiCalculator {
  constructor({ name, translations }) {
    this.name = name;
    this.templates = null;
    this.id = null;
    this.translations = { ...globalTranslations, ...translations };
  }

  /**
   * Shortcut for translate.
   *
   * @param key
   *   The key.
   * @param values
   *   The values to replace in the translation.
   *
   * @returns {*|string}
   *   Returns the translated string.
   */
  t(key, values) {
    return this.translate(key, values);
  }

  /**
   * Translate a string.
   *
   * @param key
   *   The key.
   * @param values
   *   The values to replace in translation.
   *
   * @returns {*|string}
   *   Returns the translated string.
   */
  translate(key, values) {
    if (!this.translations) {
      throw new Error('Translations are missing');
    }

    // https://stackoverflow.com/a/41540381
    function index(obj, is, value) {
      if (typeof is === 'string') {
        is = is.split('.');
      }
      if (is.length === 1 && value !== undefined) {
        obj[is[0]] = value;
        return value;
      }
      if (is.length === 0) {
        return obj;
      }
      return index(obj[is[0]], is.slice(1), value);
    }

    const lang = drupalSettings.path.currentLanguage || 'fi';
    const translation = this.translations[key] ? this.translations[key][lang] : null;
    if (translation) {
      return translation.replace(/\$\{.+?\}/g, (match) => {
        const stripDollarAndParenthesis = match.replace(/(^\$\{|\}$)/g, '');
        return index(values, stripDollarAndParenthesis);
      });
    }
    return `Missing translation: ${key}:${lang}`;
  }

  /**
   * Parse settings JSON.
   *
   * @param settings
   *   Settings JSON to parse.
   *
   * @returns {*}
   *   Returns parsed settings.
   */
  parseSettings(settings) {
    let parsed;
    try {
      parsed = JSON.parse(settings);
    } catch (e) {
      console.error(`Unable to parse the ${this.name} settings:`, settings);
      throw e;
    }
    return parsed;
  }

  /**
   * Get a DOM element by ID with the calculator's ID as suffix.
   *
   * @param {string} elemID
   *   The base ID of the element to find.
   *
   * @returns {HTMLElement}
   *   The found DOM element.
   *
   * @throws {Error}
   *   If the element is not found.
   */
  getElement(elemID) {
    const elem = document.querySelector(`#${elemID}_${this.id}`);
    if (!elem) {
      throw new Error(`Element #${elemID}_${this.id} missing from ${this.name} at getElement`);
    }

    return elem;
  }

  /**
   * Render a Mustache template with the given data.
   *
   * @param {string} template
   *   The Mustache template string.
   * @param {Object} partialData
   *   The data to render the template with.
   *
   * @returns {string}
   *   The rendered HTML.
   */
  getPartialRender(template, partialData) {
    this.preprocessData(partialData);
    return Mustache.render(template, partialData, this.templates.partials);
  }

  /**
   * Find the smallest matching value >= key in the range object.
   *
   * @param {number} value
   *   The value to find the range for.
   * @param {Object} range
   *   The range object with numeric keys.
   *
   * @returns {*}
   *   The value from the range that matches the smallest key >= value.
   *
   * @throws {Error}
   *   If no matching range is found.
   */
  getMinimumRange(value, range) {
    const rangeKeys = Object.keys(range).reverse();
    for (let i = 0; i < rangeKeys.length; i++) {
      const valueLimit = rangeKeys[i];
      if (Number(valueLimit) <= value) {
        return range[valueLimit];
      }
    }
    throw new Error(`Minimum range not found for ${value} from ${range}`);
  }

  /**
   * Clamp a value between a minimum and maximum.
   *
   * @param {number} min
   *   The minimum allowed value.
   * @param {number} value
   *   The value to clamp.
   * @param {number} max
   *   The maximum allowed value.
   *
   * @returns {number}
   *   The clamped value.
   */
  clamp(min, value, max) {
    return Math.max(min, Math.min(value, max));
  }

  /**
   * Format a number as a string with exactly two decimal places.
   *
   * @param {number} num
   *   The number to format.
   *
   * @returns {string}
   *   The formatted number as a string with exactly two decimal places.
   */
  formatEuroCents(num) {
    num = `${Math.round(num * 100) / 100}`;
    const decimalPos = num.indexOf('.');
    if (decimalPos === -1) {
      num += '.00';
    } else if (num.length - decimalPos === 2) {
      num += '0';
    }
    return num;
  }

  /**
   * Format a number as a string with Finnish euro cents style.
   *
   * @param {number} num
   *   The number to format.
   *
   * @returns {string}
   *   The formatted number with comma as decimal separator.
   */
  formatFinnishEuroCents(num) {
    return this.formatEuroCents(num).replace('.', ',');
  }

  /**
   * Preprocess the data before rendering templates.
   *
   * @param {Object} obj
   *   The data object to preprocess.
   */
  preprocessData(obj) {
    const keys = Object.keys(obj);
    for (let i = 0; i < keys.length; i++) {
      const value = obj[keys[i]];
      if (typeof value === 'object' && value !== null) {
        this.preprocessData(value);
        if (!Object.hasOwn(value, 'items')) {
          value.items = null;
        }
        if (!Object.hasOwn(value, 'group')) {
          value.group = null;
        }
        if (!Object.hasOwn(value, 'dynamic_area')) {
          value.dynamic_area = null;
        }
      } else if (typeof value === 'number') {
        // Convert numeric values to strings so that
        // the mustache does not think 0 === false and skip it.
        obj[keys[i]] += '';
      }
    }
  }

  /**
   * Get the value of a form field.
   *
   * @param {string} elemID
   *   The ID of the form field.
   *
   * @returns {string|number}
   *   The field value, or null if empty.
   */
  getFieldValue(elemID) {
    const elem = document.querySelector(`#${elemID}_${this.id}`);
    if (!elem) {
      throw new Error(`Element #${elemID}_${this.id} missing from ${this.name} at getFieldValue`);
    }

    if (elem.dataset?.type === 'radio') {
      const checked = elem.querySelector('input:checked');
      if (checked) {
        return checked.value;
      }
      return null;
    }

    if (elem.dataset?.type === 'checkbox') {
      return elem.checked;
    }

    if (elem.dataset?.type === 'input_integer' || elem.dataset?.type === 'input_float') {
      // Check that required input has value.
      if (elem.value === 'undefined' || elem.value === '') {
        return null;
      }

      let elemValue = elem.value.replace(',', '.');

      if (elem.dataset.strip) {
        const regex = new RegExp(elem.dataset.strip, 'g');
        elemValue = elemValue.replaceAll(regex, '');
      }

      if (elem.dataset.type === 'input_integer' && Number.isNaN(Number.parseInt(elemValue, 10))) {
        return null;
      }

      if (elem.dataset.type === 'input_float' && Number.isNaN(Number.parseFloat(elemValue))) {
        return null;
      }

      return elemValue;
    }
  }

  /**
   * Get an error message for a form field.
   *
   * @param {string} elemID
   *   The ID of the form field.
   * @param {string} translationKey
   *   The translation key for the error message.
   * @param {Object} translationParams
   *   Parameters for the translation.
   *
   * @returns {string}
   *   The translated error message.
   */
  getError(elemID, translationKey, translationParams) {
    const elem = document.querySelector(`#${elemID}_${this.id}`);
    if (!elem) {
      throw new Error(`Element #${elemID}_${this.id} missing from ${this.name} at validateBasics`);
    }

    const labelText = document.querySelector(`#labelText_${elem.id}`)?.innerText || elem.id;
    let labelLink = `<a href="#${elem.id}">${labelText}</a>`;

    if (elem.tagName === 'FIELDSET') {
      const firstRadio = elem.querySelector('input[type="radio"]');
      if (firstRadio) {
        labelLink = `<a href="#${firstRadio.id}">${labelText}</a>`;
      }
    }

    const error = this.translate(translationKey, { labelLink, labelText, ...translationParams });
    const errorHtml = `<span class="hdbt-error-text">${error}.</span>`;

    const elemFormItem = elem.closest('.form-item');
    if (elemFormItem) {
      elemFormItem.classList.add('hds-text-input--invalid');
      const errorContainer = elemFormItem.querySelector('.helfi-calculator__error-placeholder');
      if (errorContainer) {
        errorContainer.innerHTML = errorHtml;
      }
    }

    const errorWithLink = this.translate('error_with_link', { error, labelLink });
    return [errorWithLink];
  }

  /**
   * Validate basic form field requirements.
   *
   * @param {string} elemID
   *   The ID of the form field to validate.
   *
   * @returns {Array}
   *   Array of error messages, empty if valid.
   */
  validateBasics(elemID) {
    const elem = document.querySelector(`#${elemID}_${this.id}`);
    if (!elem) {
      throw new Error(`Element #${elemID}_${this.id} missing from ${this.name} at validateBasics`);
    }

    if (elem.dataset?.type === 'radio') {
      const checked = elem.querySelector('input:checked');
      if (!checked && elem.dataset.required) {
        return this.getError(elemID, 'select_radio');
      }
    }

    // Check number inputs.
    if (elem.dataset?.type === 'input_integer' || elem.dataset?.type === 'input_float') {
      // Optional empty inputs can be ignored.
      if (!elem.dataset.required && (typeof elem.value === 'undefined' || elem.value === '')) {
        return [];
      }

      // Check that required input has value.
      if (elem.value === 'undefined' || elem.value === '') {
        return this.getError(elemID, 'enter_value');
      }

      let elemValue = elem.value.replace(',', '.');

      if (elem.dataset.strip) {
        const regex = new RegExp(elem.dataset.strip, 'g');
        elemValue = elemValue.replaceAll(regex, '');
      }

      // Check if it's an integer number.
      const integerRegex = /^-?([1-9][0-9]*|0)$/;
      if (elem.dataset.type === 'input_integer' && !integerRegex.test(elemValue)) {
        return this.getError(elemID, 'must_be_whole_number');
      }

      // Check if it's a decimal number or integer.
      const floatRegex = /^-?([1-9][0-9]*|0)(\.[0-9]+)?$/;
      if (elem.dataset.type === 'input_float' && !floatRegex.test(elemValue)) {
        return this.getError(elemID, 'must_be_number');
      }

      // If both bounds are set.
      if (typeof elem.dataset.min !== 'undefined' && typeof elem.dataset.max !== 'undefined') {
        if (
          Number.parseFloat(elem.dataset.min) > Number.parseFloat(elemValue) ||
          elemValue > Number.parseFloat(elem.dataset.max)
        ) {
          return this.getError(elemID, 'min_or_max_out_of_bounds', { min: elem.dataset.min, max: elem.dataset.max });
        }
        // Less than min.
      } else if (typeof elem.dataset.min !== 'undefined') {
        if (Number.parseFloat(elem.dataset.min) > Number.parseFloat(elemValue)) {
          return this.getError(elemID, 'min_out_of_bounds', { min: elem.dataset.min });
        }
        // More than max.
      } else if (typeof elem.dataset.max !== 'undefined') {
        if (Number.parseFloat(elemValue) > Number.parseFloat(elem.dataset.max)) {
          return this.getError(elemID, 'max_out_of_bounds', { max: elem.dataset.max });
        }
      }
    }
    return [];
  }

  /**
   * Hide a form group by ID.
   *
   * @param {string} id
   *   The ID of the group to hide.
   */
  hideGroup(id) {
    const elem = document.querySelector(`#${id}_${this.id}:not([data-hide-group='true'])`);
    if (elem?.dataset) {
      elem.dataset.hideGroup = true;
    }
  }

  /**
   * Show a form group by ID.
   *
   * @param {string} id
   *   The ID of the group to show.
   */
  showGroup(id) {
    const elem = document.querySelector(`#${id}_${this.id}[data-hide-group='true']`);
    if (elem?.dataset) {
      elem.dataset.hideGroup = false;
    }
  }

  /**
   * Show text in an ARIA live region.
   *
   * @param {string} text
   *   The text to announce to screen readers.
   */
  showAriaLiveText(text) {
    const ariaLiveElem = document.getElementById(`aria_live_${this.id}`);
    ariaLiveElem.innerText = text;
    window.setTimeout(() => {
      ariaLiveElem.innerText = '';
    }, 1000);
  }

  /**
   * Renders a notification message in the UI.
   *
   * @static
   * @param {HTMLElement} element
   *   The DOM element where the notification will be rendered.
   * @param {string} notificationClass
   *   CSS class(es) for the notification type (e.g., 'hds-notification--error').
   * @param {string} notificationIcon
   *   The icon identifier to display in the notification.
   * @param {Object} result
   *   The result object containing the notification content.
   * @param {string|Array} result.message
   *   The message to display (can be a string or array of strings).
   * @param {string} result.title
   *   The title of the notification.
   * @param {string} notificationAriaLabel
   *   The ARIA label for the notification section.
   */
  static renderNotification(element, notificationClass, notificationIcon, result, notificationAriaLabel) {
    let { message } = result;
    if (Array.isArray(result.message) && result.message.length > 1) {
      message = `<ul class="messages__list"><li class="messages__list__item">${result.message.join('</li><li class="messages__list__item">')}</li></ul>`;
    }

    element.innerHTML = `
      <section aria-label="${notificationAriaLabel}" class="hds-notification ${notificationClass}">
        <div class="hds-notification__content">
          <h2 class="hds-notification__label">
            <span class="hds-icon hds-icon--${notificationIcon}" aria-hidden="true"></span>
            <span>${result.title}</span>
          </h2>
          <div class="hds-notification__body">${message}</div>
        </div>
      </section>`;
  }

  /**
   * Renders a receipt notification in the UI and scrolls it into view.
   *
   * @static
   * @param {HTMLElement} element
   *   The DOM element where the receipt will be rendered.
   * @param {string} notificationClass
   *   CSS class(es) for the receipt notification type.
   * @param {string} notificationIcon
   *   The icon identifier to display in the receipt.
   * @param {Object} result
   *   The result object containing the receipt content.
   * @param {string|Array} result.message
   *   The message to display (can be a string or array of strings).
   * @param {string} result.title
   *   The title of the receipt.
   * @param {string} notificationAriaLabel
   *   The ARIA label for the receipt section.
   */
  static renderReceipt(element, notificationClass, notificationIcon, result, notificationAriaLabel) {
    let { message } = result;
    if (Array.isArray(result.message) && result.message.length > 1) {
      message = `<ul class="messages__list"><li class="messages__list__item">${result.message.join('</li><li class="messages__list__item">')}</li></ul>`;
    }

    const html = `
      <section aria-label="${notificationAriaLabel}" class="hds-notification ${notificationClass}">
        <div class="hds-notification__content">
          <h2 class="hds-notification__label">
            <span class="hds-icon hds-icon--${notificationIcon}" aria-hidden="true"></span>
            <span>${result.title}</span>
          </h2>
          <div class="hds-notification__body">${message}</div>
        </div>
      </section>`;

    element.innerHTML = '';
    element.insertAdjacentHTML('beforeend', html);
    element.scrollIntoView({ behavior: 'smooth', block: 'end', inline: 'nearest' });
  }

  /**
   * Set focus to the receipt heading.
   *
   * @param {HTMLElement} element
   *   The receipt container element.
   */
  focusReceiptHeading(element) {
    const titleElem = element.querySelector('h2');
    titleElem.setAttribute('tabindex', '0');
    titleElem.focus();
    titleElem.setAttribute('tabindex', '-1');
  }

  /**
   * Render the calculator result or error.
   *
   * @param {Object} result
   *   The result object containing either receipt or error information.
   */
  renderResult(result) {
    if (result.error) {
      HelfiCalculator.renderNotification(
        document.querySelector(`#${this.id} .helfi-calculator-notification--error`),
        'hds-notification--error',
        'error-fill',
        result.error,
        this.translate('notification_aria_label_for_error'),
      );
      const titleElem = document.querySelector(
        `#${this.id} .helfi-calculator-notification--error .hds-notification__label`,
      );
      titleElem.setAttribute('tabindex', '0');
      titleElem.focus();
      titleElem.scrollIntoViewIfNeeded();
      titleElem.setAttribute('tabindex', '-1');
    }

    if (result.ariaLive) {
      this.showAriaLiveText(result.ariaLive);
    }

    if (result.receipt) {
      const element = document.querySelector(`#${this.id} .helfi-calculator-notification--result`);
      element.innerHTML = result.receipt;
      element.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' }); // Scroll receipt into view while it's animating.
      window.setTimeout(() => {
        this.focusReceiptHeading(element);
      }, this.receiptOpenMs + 10); // Add 10ms after animation so that title is in place when focusing into it.
    } else if (result.alert) {
      HelfiCalculator.renderNotification(
        document.querySelector(`#${this.id} .helfi-calculator-notification--result`),
        'hds-notification--alert',
        'alert-circle-fill',
        result.alert,
        this.translate('notification_aria_label_for_alert'),
      );
    } else if (result.info) {
      HelfiCalculator.renderReceipt(
        document.querySelector(`#${this.id} .helfi-calculator-notification--result`),
        'hds-notification--info',
        'info-circle-fill',
        result.info,
        this.translate('notification_aria_label_for_info'),
      );
    }
  }

  /**
   * Clear the current result display.
   */
  clearResult() {
    document.querySelector(`#${this.id} .helfi-calculator-notification--error`).innerHTML = '';
    document.querySelector(`#${this.id} .helfi-calculator-notification--result`).innerHTML = '';
    const errors = document.querySelectorAll(`#${this.id} .hds-text-input--invalid`);
    Object.values(errors).forEach((error) => {
      error.classList.remove('hds-text-input--invalid');
    });
    const errorsMessages = document.querySelectorAll(`#${this.id} .helfi-calculator__error-placeholder`);
    Object.values(errorsMessages).forEach((errorMessage) => {
      errorMessage.innerHTML = '';
    });
  }

  /**
   * Initialize the calculator.
   *
   * @param {Object} params
   *   The initialization parameters.
   * @param {string} params.id
   *   The ID for the calculator instance.
   * @param {Object} params.formData
   *   The form data for the calculator.
   * @param {Object} params.eventHandlers
   *   Event handlers for the calculator form.
   */
  init({ id, formData, eventHandlers }) {
    this.initParams = { id, formData, eventHandlers };
    this.id = id;
    this.receiptOpenMs = 300; // Should be same as in src/scss/06_components/helfi_calculator/_helfi_calculator.scss:141 with.helfi-calculator__receipt animation-duration.

    this.templates = {
      form: `
        <div class="visually-hidden" aria-live="polite" aria-atomic="true" id="aria_live_{{form_id}}"></div>
        <div class="helfi-calculator-disclaimer">
          {{#has_required_fields}}
            ${this.translate('has_required_fields', { required: '{{>required_explanation}} {{>required}}' })}
          {{/has_required_fields}}
          ${this.translate('not_saved', { calculate: this.translate('calculate') })}
        </div>
        <div class="helfi-calculator-notification helfi-calculator-notification--error" aria-live="polite" aria-atomic="true"></div>
        <form class="helfi-calculator" action="" method="post">
          {{#items}}
            {{>form_item}}
          {{/items}}
          <div class="helfi-calculator__buttons">
            <input type="submit" enterkeyhint="done" value="{{#submit}}{{submit}}{{/submit}}{{^submit}}${this.translate('calculate')}{{/submit}}" class="hds-button hds-button--primary">
            <input type="reset" value="{{#reset}}{{reset}}{{/reset}}{{^reset}}${this.translate('reset')}{{/reset}}" class="hds-button hds-button--secondary">
          </div>
        </form>
        <div class="helfi-calculator-notification helfi-calculator-notification--result"></div>
      `,
      partials: {
        required: `
          <span class="visually-hidden">${this.translate('required')}</span><span aria-hidden="true" class="helfi-calculator-required">*</span>`,
        required_explanation: `
          <span class="visually-hidden">${this.translate('required_explanation')}</span>
        `,
        form_item: `
          <div class="helfi-calculator__item">
            {{#group}}{{>group}}{{/group}}
            {{#dynamic_area}}{{>dynamic_area}}{{/dynamic_area}}
            {{#heading}}{{>heading}}{{/heading}}
            {{#legend}}{{>legend}}{{/legend}}
            {{#paragraph}}{{>paragraph}}{{/paragraph}}
            {{#hr}}{{>hr}}{{/hr}}
            {{#input}}{{>input}}{{/input}}
            {{#input_integer}}{{>input_integer}}{{/input_integer}}
            {{#input_float}}{{>input_float}}{{/input_float}}
            {{#radio}}{{>radio}}{{/radio}}
            {{#checkbox}}{{>checkbox}}{{/checkbox}}
          </div>
        `,
        group: `
          <div id="{{id}}_{{form_id}}" class="helfi-calculator__group" {{#hide_group}}data-hide-group="true"{{/hide_group}}>
            <div>
              {{#items}}
                {{>form_item}}
              {{/items}}
            </div>
          </div>
        `,
        dynamic_slot: `
          <div id="{{id}}_{{form_id}}" class="helfi-calculator__dynamic-slot" {{#slotNumber}}data-slot-number="{{slotNumber}}"{{/slotNumber}}>
            <fieldset class="helfi-calculator__dynamic_slot__fieldset">
              {{#items}}
                {{>form_item}}
              {{/items}}
              {{#remove_label}}
                <div class="helfi-calculator__dynamic-remove-wrapper"><button class="helfi-calculator__dynamic-remove hds-button hds-button--supplementary"><span class="hds-button__label">{{remove_label}}</span><span class="hel-icon hel-icon--cross" role="img" aria-hidden="true"></button></div>
              {{/remove_label}}
            </fieldset>
          </div>
        `,
        dynamic_area: `
          <div id="{{id}}_{{form_id}}" class="helfi-calculator__dynamic-area">
            <div id="slots_{{id}}_{{form_id}}" class="helfi-calculator__dynamic-area__slots">
              {{#dynamic_slots}}
                {{>dynamic_slot}}
              {{/dynamic_slots}}
            </div>
            {{#add_button_label}}
              <button id="add-button_{{id}}_{{form_id}}" class="hds-button hds-button--secondary"><span class="hel-icon hel-icon--plus" role="img" aria-hidden="true"></span><span class="hds-button__label">{{add_button_label}}</span></button>
            {{/add_button_label}}
          </div>
        `,
        heading: `
          <h{{level}}{{^level}}2{{/level}}>{{text}}</h{{level}}{{^level}}2{{/level}}>
        `,
        legend: `
          <legend class="helfi-calculator__legend helfi-calculator__legend--level_{{level}}{{^level}}2{{/level}}">{{text}}</legend>
        `,
        paragraph: `
          <p{{#class}} class="{{class}}"{{/class}}>{{text}}</p>
        `,
        hr: `
          <hr>
        `,
        label: `
          <label
            class="hds-text-input__label"
            for="{{id}}_{{form_id}}"
            id="label_{{id}}_{{form_id}}"
            ><span
              id="labelText_{{id}}_{{form_id}}"
              class="label_text"
              >{{label}}</span>{{#unit}} ({{unit}}){{/unit}}{{#required}}{{>required}}{{/required}}</label>
        `,
        error_placeholder: `
          <div class="helfi-calculator__error-placeholder" id="error_text_{{id}}_{{form_id}}"></div>
        `,
        helper_text: `
          <span class="hdbt-helper-text" id="helper_text_{{id}}_{{form_id}}">{{helper_text}}</span>
        `,
        input: `
          <div class="form-item hds-text-input {{#required}}input--required{{/required}}">
            {{>label}}
            <div class="hds-text-input__input-wrapper">
              <input
                type="{{type}}"
                data-type="input"
                id="{{id}}_{{form_id}}"
                name="{{id}}"
                {{#inputmode}}inputmode="{{inputmode}}"{{/inputmode}}
                {{#pattern}}pattern="{{pattern}}"{{/pattern}}
                {{#min}}data-min="{{min}}"{{/min}}
                {{#max}}data-max="{{max}}"{{/max}}
                {{#size}}size="{{size}}"{{/size}}
                {{#maxlength}}maxlength="{{maxlength}}"{{/maxlength}}
                {{#required}}data-required="required"{{/required}}
                {{#strip}}data-strip="{{strip}}"{{/strip}}
                {{#label}}data-label="{{label}}"{{/label}}
                {{#value}}value="{{value}}"{{/value}}
                aria-describedby="error_text_{{id}}_{{form_id}}{{#helper_text}} helper_text_{{id}}_{{form_id}}{{/helper_text}}"
                class="form-text hds-text-input__input">
            </div>
            {{>error_placeholder}}
            {{>helper_text}}
          </div>
        `,
        input_integer: `
          <div class="form-item hds-text-input {{#required}}input--required{{/required}}">
            {{>label}}
            <div class="hds-text-input__input-wrapper">
              <input
                type="text"
                data-type="input_integer"
                id="{{id}}_{{form_id}}"
                name="{{id}}"
                inputmode="numeric"
                {{#min}}data-min="{{min}}"{{/min}}
                {{#max}}data-max="{{max}}"{{/max}}
                {{#size}}size="{{size}}"{{/size}}
                {{#maxlength}}maxlength="{{maxlength}}"{{/maxlength}}
                {{#required}}data-required="required"{{/required}}
                {{#strip}}data-strip="{{strip}}"{{/strip}}
                data-label="label_{{id}}_{{form_id}}"
                {{#value}}value="{{value}}"{{/value}}
                aria-describedby="error_text_{{id}}_{{form_id}}{{#helper_text}} helper_text_{{id}}_{{form_id}}{{/helper_text}}"
                class="form-text hds-text-input__input">
            </div>
            {{>error_placeholder}}
            {{>helper_text}}
          </div>
        `,
        input_float: `
          <div class="form-item hds-text-input {{#required}}input--required{{/required}}">
            {{>label}}
            <div class="hds-text-input__input-wrapper">
              <input
                type="text"
                data-type="input_float"
                id="{{id}}_{{form_id}}"
                name="{{id}}"
                {{#min}}data-min="{{min}}"{{/min}}
                {{#max}}data-max="{{max}}"{{/max}}
                {{#size}}size="{{size}}"{{/size}}
                {{#maxlength}}maxlength="{{maxlength}}"{{/maxlength}}
                {{#required}}data-required="required"{{/required}}
                {{#strip}}data-strip="{{strip}}"{{/strip}}
                {{#value}}value="{{value}}"{{/value}}
                aria-describedby="error_text_{{id}}_{{form_id}}{{#helper_text}} helper_text_{{id}}_{{form_id}}{{/helper_text}}"
                class="form-text hds-text-input__input">
            </div>
            {{>error_placeholder}}
            {{>helper_text}}
          </div>
        `,
        checkbox: `
          <div class="hds-checkbox">
            <input
              type="checkbox"
              data-type="checkbox"
              id="{{id}}_{{form_id}}"
              class="hds-checkbox__input"
              aria-describedby="error_text_{{id}}_{{form_id}}{{#helper_text}} helper_text_{{id}}_{{form_id}}{{/helper_text}}"
              {{#checked}}checked{{/checked}}
              >
            <label
              class="hds-checkbox__label"
              for="{{id}}_{{form_id}}"
              id="label_{{id}}_{{form_id}}"
              ><span
                id="labelText_{{id}}_{{form_id}}"
                class="label_text"
                >{{label}}</span></label>
            {{>error_placeholder}}
            {{>helper_text}}
          </div>
        `,
        radio: `
          <fieldset
            data-type="radio"
            id="{{id}}_{{form_id}}"
            {{#required}}data-required="true"{{/required}}
            class="form-item hds-selection-group {{#required}}input--required{{/required}}"
            >
            <legend
              class="hds-selection-group__legend"
              id="label_{{id}}_{{form_id}}"
              aria-describedby="error_text_{{id}}_{{form_id}}{{#helper_text}} helper_text_{{id}}_{{form_id}}{{/helper_text}}"
              ><span
                id="labelText_{{id}}_{{form_id}}"
                class="label_text"
                >{{label}}</span>{{#unit}} ({{unit}}){{/unit}}{{#required}}{{>required}}{{/required}}</legend>
            <div class="hds-selection-group__items">
              {{#radio_items}}
                {{>radio_item}}
              {{/radio_items}}
            </div>
            {{>error_placeholder}}
            {{>helper_text}}
          </fieldset>
        `,
        radio_item: `
          <div class="hds-selection-group__item">
            <div class="hds-radio-button">
              <input
                type="radio"
                id="{{item_id}}_{{form_id}}"
                name="{{name}}"
                {{#required}}data-required="required"{{/required}}
                data-label="label_{{id}}_{{form_id}}"
                value="{{value}}"
                class="hds-radio-button__input">
              <label for="{{item_id}}_{{form_id}}" class="hds-radio-button__label">{{label}}</label>
            </div>
          </div>
        `,
        receipt: `
          <div class="helfi-calculator__receipt" id="receipt_{{id}}">
            <div class="helfi-calculator__receipt__wrapper">
              <div class="helfi-calculator__receipt__container">
                <h2>{{title}}</h2>
                <p class="helfi-calculator__receipt-total">
                  <span class="helfi-calculator__receipt-total__prefix">{{total_prefix}}</span>
                  <span class="helfi-calculator__receipt-total__value">{{total_value}}</span>
                  <span class="helfi-calculator__receipt-total__suffix">{{total_suffix}}</span>
                </p>
                {{#total_explanation}}
                  <p class="helfi-calculator__receipt-total-explanation">{{total_explanation}}</p>
                {{/total_explanation}}
                {{#hr}}
                  <hr class="helfi-calculator__receipt-hr" />
                {{/hr}}
                {{#breakdown}}
                  <h3>{{title}}</h3>
                  {{#subtotals}}
                    {{>subtotal}}
                  {{/subtotals}}
                  {{#additional_details}}
                    {{#title}}<h4>{{.}}</h4>{{/title}}
                    {{#text}}<p>{{.}}</p>{{/text}}
                  {{/additional_details}}
                {{/breakdown}}
              </div>
            </div>
          </div>
        `,
        subtotal: `
          <div class="helfi-calculator__receipt-subtotal">
            <h4>{{title}}</h4>
            <span class="helfi-calculator__receipt-subtotal-sum">
              {{#sum_screenreader}}
                <span aria-hidden="true">{{sum}}</span>
                <span class="visually-hidden">{{sum_screenreader}}</span>
              {{/sum_screenreader}}
              {{^sum_screenreader}}
                {{sum}}
              {{/sum_screenreader}}
            </span>
            {{#has_details}}
              <ul>
              {{#details}}
                <li>{{.}}</li>
              {{/details}}
              </ul>
            {{/has_details}}
          </div>
        `,
      },
    };

    this.preprocessData(formData);
    const processedData = formData;

    const render = Mustache.render(this.templates.form, processedData, this.templates.partials);

    document.getElementById(this.id).innerHTML = render;

    const events = Object.keys(eventHandlers);
    for (let i = 0; i < events.length; i++) {
      const event = events[i];
      document.getElementById(id).addEventListener(event, eventHandlers[event]);
    }
  }
}

/**
 * Initialize the calculator.
 *
 * @param {Object} translations
 *   The translations for the calculator.
 */
window.helfiCalculator = (translations) => new HelfiCalculator(translations);
