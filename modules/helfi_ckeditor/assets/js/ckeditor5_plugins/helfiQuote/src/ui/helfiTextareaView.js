import { LabeledFieldView, View } from 'ckeditor5/src/ui';
import { FocusTracker } from 'ckeditor5/src/utils';

/**
 * The textareaView class.
 */
export default class TextareaView extends View {

  /**
   * @inheritDoc
   */
  constructor(locale, editor) {
    super(locale, editor);

    this.textAreaLabel = Drupal.t('Quotation', {}, { context: 'CKEditor5 Helfi Quote plugin' });

    this.set('value', undefined);
    this.set('id', undefined);

    this.set('label');

    this.focusTracker = new FocusTracker();
    this.bind('isFocused').to(this.focusTracker);
    this.set('isEmpty', true);

    this.children = this.createCollection();
    this.textArea = this._createTextareaView(locale);

    this.setTemplate({
      tag: 'div',
      attributes: {
        class: [
          'ck-helfi-textarea',
        ],
      },
      children: this.children,
    });
  }

  /**
   * @inheritDoc
   */
  render() {
    super.render();
    this.children.add(this.textArea);
    this.focusTracker.add(this.textArea);
    this.focusTracker.add(this.textArea.fieldView.element);

    this._setDomElementValue(this.value);
    this._updateValue();
  }

  /**
   * @inheritDoc
   */
  destroy() {
    super.destroy();
    this.value = '';
    this.focusTracker.destroy();
  }

  /**
   * Creates a textarea element.
   *
   * @param {Object} locale The localization services instance.
   * @return {LabeledFieldView} The labeled field view for the textarea.
   */
  _createTextareaView(locale) {
    const bind = this.bindTemplate;

    const labeledTextareaView = new LabeledFieldView(locale, (labeledFieldView, viewUid) => {
      const textareaView = new View(labeledFieldView.locale);

      /**
       *  <textarea id="{id}" name="{id}>{placeholder}</textarea>
       */
      textareaView.setTemplate({
        tag: 'textarea',
        attributes: {
          rows: 5,
          cols: 40,
          id: viewUid,
          class: [
            'ck',
            'ck-input',
            'ck-helfi-textarea',
            bind.if('isEmpty', 'ck-input_is-empty'),
            bind.if('isFocused', 'ck-input_focused'),
          ],
        },
        on: {
          input: bind.to((...args) => {
            this.fire('input', ...args);
            this._updateValue();
          }),
          change: bind.to(this._updateValue.bind(this))
        }
      });

      textareaView.bind('isFocused').to(labeledFieldView, 'isFocused');
      labeledFieldView.bind('isFocused').to(textareaView, 'isFocused');
      return textareaView;
    });

    labeledTextareaView.label = this.textAreaLabel;

    return labeledTextareaView;
  }

  /**
   * Focuses the textarea field view element.
   */
  focus() {
    this.textArea.fieldView.element.focus();
  }

  /**
   * Sets the `value` property of the element on demand.
   *
   * @param {any} value The value to be added to the textarea element.
   */
  _setDomElementValue(value) {
    this.element.value = (!value && value !== 0) ? '' : value;
    this.textArea.fieldView.element.value = this.element.value;
  }

  /**
   * Updates the isEmpty property value on demand.
   */
  _updateValue() {
    this.value = this.textArea.fieldView.element.value
      ? this.textArea.fieldView.element.value
      : false;
    this.isEmpty = !this.value;
  }

  /**
   * Updates the isEmpty property value on demand.
   *
   * @param {string} value The value to be added to the textarea element.
   */
  updateValueBasedOnSelection(value = '') {
    this.isEmpty = !value;
    this._setDomElementValue(value);
  }

}
