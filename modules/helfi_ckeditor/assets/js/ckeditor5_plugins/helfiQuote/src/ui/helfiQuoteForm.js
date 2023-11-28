import {
  ButtonView,
  createLabeledInputText,
  LabeledFieldView,
  submitHandler,
  View
} from 'ckeditor5/src/ui';
import { KeystrokeHandler } from 'ckeditor5/src/utils';
import { icons } from 'ckeditor5/src/core';
import TextareaView from './helfiTextareaView';

/**
 * The textareaView class.
 */
export default class HelfiQuoteForm extends View {

  /**
   * @inheritDoc
   */
  constructor(locale, editor) {
    super(locale, editor);

    this.editor = editor;
    this.textAreaView = new TextareaView(locale, editor);

    this.authorInputView = new LabeledFieldView(editor.locale, createLabeledInputText);
    this.authorInputView.label = Drupal.t('Source / author', {}, { context: 'CKEditor5 Helfi Quote plugin' });

    this.saveButtonView = this._createButton(
      Drupal.t('Save', {}, { context: 'CKEditor5 Helfi Quote plugin' }),
      icons.check,
      'ck-button-save'
    );
    this.saveButtonView.type = 'submit';

    this.cancelButtonView = this._createButton(
      Drupal.t('Cancel', {}, { context: 'CKEditor5 Helfi Quote plugin' }),
      icons.cancel,
      'ck-button-cancel',
      'cancel'
    );

    this.keystrokes = new KeystrokeHandler();
    this.children = this.createCollection();

    this.setTemplate({
      tag: 'form',

      attributes: {
        class: [ 'ck', 'ck-helfi-quote-form' ],

        // https://github.com/ckeditor/ckeditor5-link/issues/90
        tabindex: '-1'
      },

      children: this.children
    });
  }

  /**
   * @inheritDoc
   */
  render() {
    super.render();

    submitHandler({
      view: this
    });

    this.children.add(this.textAreaView);
    this.children.add(this.authorInputView);
    this.children.add(this.saveButtonView);
    this.children.add(this.cancelButtonView);

    this.keystrokes.listenTo(this.element);
  }

  /**
   * @inheritDoc
   */
  destroy() {
    super.destroy();

    // Blur the input element before removing it from DOM
    // to prevent issues in some browsers.
    // See https://github.com/ckeditor/ckeditor5/issues/1501.
    this.saveButtonView.focus();

    // Because the form has an input which has focus, the focus must be
    // brought back to the editor. Otherwise, it would be lost.
    this.editor.editing.view.focus();

    // Handle also HelfiTextareaView destroy.
    this.textAreaView.destroy();
  }

  /**
   * Focuses the textarea field when the quote form is opened.
   */
  focus() {
    this?.textAreaView?.children?.first?.fieldView?.element.focus();
  }

  /**
   * Creates a button view.
   *
   * @param {string} label The button label.
   * @param {string} icon The button icon.
   * @param {string} className The additional button CSS class name.
   * @param {string|boolean} eventName An event name that the `ButtonView#execute` event will be delegated to.
   * @return {ButtonView} The button view instance.
   */
  _createButton(label, icon, className, eventName = false) {
    const button = new ButtonView(this.locale);

    button.set({
      label,
      icon,
      tooltip: true
    });

    button.extendTemplate({
      attributes: {
        class: className
      }
    });

    if (eventName) {
      button.delegate('execute').to(this, eventName);
    }

    return button;
  }


}
