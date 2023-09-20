/**
 * @file registers the helfiQuoteUi plugin and binds functionality to it.
 */
import { Plugin } from 'ckeditor5/src/core';
import { createDropdown } from 'ckeditor5/src/ui';
import icon from '../../../../icons/helfiQuote.svg';
import HelfiQuoteForm from './ui/helfiQuoteForm';

export default class HelfiQuoteUi extends Plugin {

  constructor(editor) {
    super(editor);
    this.editor = editor;
    this.updateSelection = false;
    this.quoteFormView = false;
    this.dropdownView = false;
  }

  init() {
    const { editor } = this;
    const defaultTitle = Drupal.t('Add a quote', {}, { context: 'CKEditor5 Helfi Quote plugin' });

    // Register the helfiQuote toolbar button.
    editor.ui.componentFactory.add('helfiQuote', (locale) => {
      const quoteCommand = this.editor.commands.get('helfiQuoteCommand');

      // Create the dropdown view.
      this.dropdownView = createDropdown(locale);

      // Create the toolbar button.
      this.dropdownView.buttonView.set({
        label: defaultTitle,
        icon,
        tooltip: true,
      });

      // Rebind the state of the button to quoteCommand isEnabled observable.
      this.dropdownView.buttonView.unbind('isEnabled');
      this.dropdownView.buttonView.bind('isEnabled').to(quoteCommand, 'isEnabled');

      // Add class for the dropdown view.
      this.dropdownView.extendTemplate({
        attributes: {
          class: [ 'helfi-quote']
        }
      });

      // Add custom classes for the dropdown panel view.
      this.dropdownView.panelView.extendTemplate({
        attributes: {
          class: [
            'helfi-quote__dropdown-panel',
            'ck-reset_all-excluded',
          ]
        }
      });

      // Act on when dropdownView is opened.
      this.dropdownView.on('change:isOpen', () => {

        // No need to reinitialize the select list view.
        if (this.quoteFormView) { return; }

        // Initialize the quoteFormView.
        this.quoteFormView = new HelfiQuoteForm(locale, this.editor);

        // Execute link command after clicking the "Save" button.
        this.listenTo(this.quoteFormView, 'submit', () => {
          const quoteText = this.quoteFormView.textAreaView.textArea.fieldView.element.value || false;
          const author = this.quoteFormView.authorInputView.fieldView.element.value || false;
          quoteCommand.execute({ quoteText, author });
          this._closeFormView();
        });

        // Hide the panel after clicking the "Cancel" button.
        this.listenTo(this.quoteFormView, 'cancel', () => {
          this._closeFormView();
        });

        // Close the panel on esc key press when the **form has focus**.
        this.quoteFormView.keystrokes.set('Esc', (data, cancel) => {
          this._closeFormView();
          cancel();
        });

        // Add the quoteFormView to dropdown panelView and set it to south-west.
        this.dropdownView.panelView.children.add(this.quoteFormView);
        this.dropdownView.panelPosition = 'sw';

        // Delegate the execute command from this.quoteFormView to this.dropdownView.
        this.quoteFormView.delegate('execute').to(this.dropdownView);

        this.quoteFormView.focus();
      });

      // Add selected text to QuoteView or if there is a selection
      // or edit existing Quote.
      this.dropdownView.on('change:isOpen', () => {
        this._updateQuoteDefaultValues();
      });

      return this.dropdownView;
    });

  }

  /**
   * Add the selected text to Quote as a default value or edit existing Quote.
   */
  _updateQuoteDefaultValues() {
    const { model } = this.editor;
    const { selection } = model.document;

    // If there is a non collapsed selection, use the selection data as
    // the default value for the textarea (quote text).
    if (this.quoteFormView) {
      if (!selection.isCollapsed) {
        const ranges = selection.getRanges();
        let range = ranges.next();

        while (!range.done) {
          const currentRange = range.value;
          const items = currentRange.getItems();
          let currentItem = items.next();

          while (!currentItem.done) {
            const item = currentItem.value;
            if (item.data) {
              if (
                item.textNode?.parent?.name === 'helfiQuoteText' ||
                item.textNode?.parent?.name === 'paragraph'
              ) {
                this.quoteFormView.textAreaView.updateValueBasedOnSelection(item.data);
              }
              this.quoteFormView.authorInputView.isEmpty = item.textNode?.parent?.name !== 'helfiQuoteFooterCite';
              this.quoteFormView.authorInputView.fieldView.element.value = item.textNode?.parent?.name === 'helfiQuoteFooterCite' ? item.data : '';

              this.quoteFormView.focus();
            }
            currentItem = items.next();
          }
          range = ranges.next();
        }
      }
      else {
        this.quoteFormView.textAreaView.updateValueBasedOnSelection();
        this.quoteFormView.authorInputView.isEmpty = true;
        this.quoteFormView.authorInputView.fieldView.element.value = '';
      }
    }
  }

  /**
   * Closes the form view and resets the quoteText and authorInput fields.
   */
  _closeFormView() {
    this.quoteFormView.textAreaView.textArea.fieldView.element.value = null;
    this.quoteFormView.authorInputView.fieldView.element.value = null;

    if (this.dropdownView) {
      this.dropdownView.isOpen = false;
    }
  }

}
