/**
 * @file
 * The quote dialog definition.
 *
 * Created out of the CKEditor Plugin SDK:
 * http://docs.ckeditor.com/#!/guide/plugin_sdk_sample_1
 */

// Quote dialog definition.
CKEDITOR.dialog.add('quoteDialog', function (editor) {
  return {
    title: editor.lang.quote.dialogTitle,
    minWidth: 400,
    minHeight: 200,

    // Dialog window contents definition.
    contents: [
      {
        id: 'tab-basic',
        label: 'Basic Settings',

        // The tab contents.
        elements: [
          {
            // Quote text input field.
            type: 'textarea',
            id: 'text',
            label: editor.lang.quote.dialogQuoteText,
            validate: CKEDITOR.dialog.validate.notEmpty(editor.lang.quote.dialogQuoteTextNotEmpty),

            // Called by the main setupContent call on dialog initialization.
            setup: function (element) {

              // Get the blockquote.quote element.
              if (element) {
                let parent = element.getAscendant('blockquote');
                if (parent && parent.hasClass('quote')) {
                  element = parent;
                }
              }

              let paragraphs = element.find('p.quote__text');
              if (paragraphs.count() > 0) {
                let quote = paragraphs.getItem(0).getText();
                for (let i = 1; i < paragraphs.count(); i++) {
                  quote += '\n' + paragraphs.getItem(i).getText();
                }
                this.setValue(quote);
              }
              else {
                this.setValue(element.getText());
              }
            },

            // Called by the main commitContent call on dialog confirmation.
            commit: function (element) {
              // Clear element HTML.
              element.setHtml('');

              // Set a <p> for each line.
              let lines = this.getValue()
              let value = lines.replace(/(?:\r\n|\r|\n)/g, '<br>');
              let p = editor.document.createElement('p');
              p.setAttribute('class', 'quote__text');
              p.setHtml(value);
              element.append(p);
            }
          },
          {
            // Quote author input field.
            type: 'text',
            id: 'author',
            label: editor.lang.quote.dialogQuoteAuthor,

            // Called by the main setupContent call on dialog initialization.
            setup: function (element) {
              // Get the blockquote.quote element.
              if (element) {
                let parent = element.getAscendant('blockquote');
                if (parent && parent.hasClass('quote')) {
                  element = parent;
                }
              }

              let citeElem = element.findOne('footer.quote__author cite');
              if (citeElem !== null) {
                this.setValue(citeElem.getText());
              }
            },

            // Called by the main commitContent call on dialog confirmation.
            commit: function (element) {
              let authorElem = element.findOne('footer.quote__author');
              let citeElem = element.findOne('footer.quote__author cite');
              if (authorElem === null || citeElem === null) {
                if (this.getValue() !== '') {
                  authorElem = editor.document.createElement('footer');
                  element.append(authorElem);
                  authorElem.setAttribute('class', 'quote__author');
                  citeElem = editor.document.createElement('cite');
                  authorElem.append(citeElem);
                  citeElem.setText(this.getValue());
                }
              }
              else {
                if (this.getValue() !== '') {
                  authorElem.setAttribute('class', 'quote__author');
                  citeElem.setText(this.getValue());
                }
                else {
                  // Author has been removed, remove authorElem.
                  authorElem.remove();
                }
              }
            }
          }
        ]
      }
    ],

    // Invoked when the dialog is loaded.
    onShow: function () {
      // Get the selection in the editor.
      let selection = editor.getSelection();

      // Get the element at the start of the selection.
      let element = selection.getStartElement();

      // Get the authorElem element closest to the selection, if any.
      if (element) {
        let parent = element.getAscendant('blockquote');
        if (parent && parent.hasClass('quote')) {
          element = parent;
        }
      }

      // Create a new <authorElem> element if it does not exist.
      if (!element || !element.hasClass('quote')) {
        element = editor.document.createElement('blockquote');
        element.addClass('quote');
        element.setAttribute('aria-label', editor.lang.quote.quoteText);
        element.setAttribute('role', 'region');
        // Flag the insertion mode for later use.
        this.insertMode = true;
      }
      else {
        this.insertMode = false;
      }

      // Store the reference to the <authorElem> element in an internal property, for later use.
      this.element = element;

      // Invoke the setup methods of all dialog elements, so they can load the element attributes.
      if (!this.insertMode) {
        this.setupContent(this.element);
      }
    },

    // This method is invoked once a user clicks the OK button, confirming the dialog.
    onOk: function () {
      let blockquote = this.element;
      // Invoke the commit methods of all dialog elements, so the <blockquote> element gets modified.
      this.commitContent(blockquote);

      // Finally, in if insert mode, inserts the element at the editor caret position.
      if (this.insertMode) {
        editor.insertElement(blockquote);
      }
    }
  };
});
