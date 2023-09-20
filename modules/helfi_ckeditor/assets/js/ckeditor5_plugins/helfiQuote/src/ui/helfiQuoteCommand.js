/**
 * @file defines helfiQuoteCommand, which is executed when the quote toolbar
 * button is pressed.
 */
import { Command } from 'ckeditor5/src/core';

/**
 * Create the helfiQuote element for the editor.
 *
 * @param {object} writer The model writer.
 * @param {string} quoteText The quote text.
 * @param {string} author The Source / author.
 * @return {*} Returns the element to be added to the editor.
 */
function createQuote(writer, quoteText, author) {
  const helfiQuote = writer.createElement('helfiQuote');
  const helfiQuoteText = writer.createElement('helfiQuoteText');
  const helfiQuoteFooter = writer.createElement('helfiQuoteFooter');
  const helfiQuoteFooterCite = writer.createElement('helfiQuoteFooterCite');

  // Append the quote text and author elements to the helfiQuote.
  writer.append(helfiQuoteText, helfiQuote);
  writer.insertText(quoteText, helfiQuoteText);

  // Do not add the author if it's not available.
  if (author) {
    writer.append(helfiQuoteFooter, helfiQuote);
    writer.append(helfiQuoteFooterCite, helfiQuoteFooter);
    writer.insertText(author, helfiQuoteFooterCite);
  }

  // Return the element to be added to the editor.
  return helfiQuote;
}

export default class HelfiQuoteCommand extends Command {

  /**
   * Executes the command.
   * Insert <helfiQuote>*</helfiQuote> at the current selection position
   * in a way that will result in creating a valid model structure.
   *
   * @param {string} quoteText The quote text.
   * @param {string} author The Source / author.
   */
  execute({ quoteText, author }) {
    const { model } = this.editor;

    model.change(writer => {
      if (!quoteText) { return; }
      model.insertContent(createQuote(writer, quoteText, author));
    });
  }

  /**
   * @inheritDoc
   */
  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    // Determine if the cursor (selection) is in a position where adding a
    // helfiQuote is permitted. This is based on the schema of the model(s)
    // currently containing the cursor.
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'helfiQuote',
    );

    // If the cursor is not in a location where a helfiQuote can be added,
    // return null so the quote toolbar button cannot be clicked.
    this.isEnabled = allowedIn !== null;

    // Set value based on selection to set it as default value for the
    // new quote.
    // this.value = this._getValueFromFirstAllowedNode();
  }

}
