/**
 * @file A view to model and model to view converters for helfiQuote.
 */
import { Plugin } from 'ckeditor5/src/core';
import { toWidget, toWidgetEditable, Widget } from 'ckeditor5/src/widget';
import HelfiQuoteCommand from './ui/helfiQuoteCommand';

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * CKEditor5 internally interacts with the helfiQuote as this model:
 * <helfiQuote>
 *   <helfiQuoteText></helfiQuoteText>
 *   <helfiQuoteFooter>
 *     <helfiQuoteFooterCite></helfiQuoteFooterCite>
 *   </helfiQuoteFooter>
 * <helfiQuote>
 *
 * Which is converted for the browser/user as this markup
 * <blockquote class="quote">
 *   <p class="quote__text"></p>
 *   <footer class="quote__author"><cite></cite></footer>
 * </blockquote>
 *
 * This file has the logic for defining the helfiQuote model,
 * and for how it is converted to standard DOM markup.
 */
export default class HelfiQuoteEditing extends Plugin {
  static get requires() {
    return [ Widget ];
  }

  init() {
    const editor = this.editor;

    this._defineSchema();
    this._defineConverters();

    // Add helfiQuoteCommand.
    editor.commands.add(
      'helfiQuoteCommand',
      new HelfiQuoteCommand(editor),
    );
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'HelfiQuoteEditing';
  }

  /**
   * This registers the structure that will be seen by CKE5 as
   *
   * <helfiQuote>
   *   <helfiQuoteText></helfiQuoteText>
   *   <helfiQuoteFooter>
   *     <helfiQuoteFooterCite></helfiQuoteFooterCite>
   *   </helfiQuoteFooter>
   * <helfiQuote>
   *
   * The logic in _defineConverters() will determine how this is converted to
   * markup.
   */
  _defineSchema() {

    const schema = this.editor.model.schema;

    schema.register('helfiQuote', {
      // Behaves like a self-contained object.
      isObject: true,
      // Allow in places where other blocks are allowed.
      allowWhere: '$block',
    });

    schema.register('helfiQuoteText', {
      // This creates a boundary for external actions such as clicking
      // and keypress. For example, when the cursor is inside this blockquote,
      // the keyboard shortcut for "select all" will be limited to the contents
      // of the box.
      isLimit: true,
      // This is only to be used within helfiQuote.
      allowIn: 'helfiQuote',
      // Allow content that is allowed in blocks (e.g. text with attributes).
      allowContentOf: '$block',
    });

    schema.register('helfiQuoteFooter', {
      isLimit: true,
      // This is only to be used within helfiQuote.
      allowIn: 'helfiQuote',
      // Allow content that is allowed in blocks (e.g. text with attributes).
      allowContentOf: '$block',
    });

    schema.register('helfiQuoteFooterCite', {
      isLimit: true,
      // This is only to be used within helfiQuoteFooter.
      allowIn: 'helfiQuoteFooter',
      // Allow content that is allowed in blocks (e.g. text with attributes).
      allowContentOf: '$block',
    });
  }

  /**
   * Converters determine how CKEditor 5 models are converted into markup and
   * vice-versa.
   *
   * <blockquote class="quote">
   *   <p class="quote__text"></p>
   *   <footer class="quote__author"><cite></cite></footer>
   * </blockquote>
   */
  _defineConverters() {
    // Converters are registered via the central editor object.
    const { conversion } = this.editor;

    // Define a function for element conversion
    const addElementConversion = (modelName, viewName, classes = null) => {
      const conversionConfig = {
        model: modelName,
        view: {
          name: viewName,
          ...(classes ? { classes } : {}),
        },
      };

      // Upcast Converters: determine how existing HTML is interpreted by the
      // editor. These trigger when an editor instance loads.
      conversion.for('upcast').elementToElement(conversionConfig);

      // Data Downcast Converters: converts stored model data into HTML.
      // These trigger when content is saved.
      conversion.for('dataDowncast').elementToElement(conversionConfig);
    };

    // If <blockquote class="quote"> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <helfiQuote> model.
    // Instances of <helfiQuote> are saved as
    // <blockquote class="quote">{{inner content}}</blockquote>.
    addElementConversion('helfiQuote', 'blockquote', 'quote');

    // If <p class="quote__text"> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <helfiQuoteText> model, provided it is a child element of <helfiQuote>,
    // as required by the schema.
    // Instances of <helfiQuoteText> are saved as
    // <p class="quote__text">{{inner content}}</p>.
    addElementConversion('helfiQuoteText', 'p', 'quote__text');

    // If <footer class="quote__author"> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <helfiQuoteFooter> model, provided it is a child element of
    // <helfiQuote>, as required by the schema.
    // Instances of <helfiQuoteFooter> are saved as
    // <footer class="quote__author">{{inner content}}</cite>.
    addElementConversion('helfiQuoteFooter', 'footer', 'quote__author');

    // If <cite> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <helfiQuoteFooterCite> model, provided it is a child element of
    // <helfiQuoteFooter>, as required by the schema.
    // Instances of <helfiQuoteFooterCite> are saved as
    // <cite>{{inner content}}</cite>.
    addElementConversion('helfiQuoteFooterCite', 'cite');

    // Editing Downcast Converters. These render the content to the user for
    // editing, i.e. this determines what gets seen in the editor. These trigger
    // after the Data Upcast Converters, and are re-triggered any time there
    // are changes to any of the models' properties.
    //
    // Convert the <helfiQuote> model into a container widget in the editor UI.
    conversion.for('editingDowncast').elementToElement({
      model: 'helfiQuote',
      view: (modelElement, { writer: viewWriter }) => {
        const blockQuote = viewWriter.createContainerElement('blockquote', {
          class: 'quote',
        });
        return toWidget(blockQuote, viewWriter);
      },
    });

    // Convert the <helfiQuoteText> model into an editable <p> widget.
    conversion.for('editingDowncast').elementToElement({
      model: 'helfiQuoteText',
      view: (modelElement, { writer: viewWriter }) => {
        const p = viewWriter.createEditableElement('p', {
          class: 'quote__text',
        });
        return toWidgetEditable(p, viewWriter);
      },
    });

    // Convert the <helfiQuoteFooter> model into a container <footer> widget.
    conversion.for('editingDowncast').elementToElement({
      model: 'helfiQuoteFooter',
      view: (modelElement, { writer: viewWriter }) => {
        const footer = viewWriter.createContainerElement('footer', {
          class: 'quote__author',
        });
        return toWidget(footer, viewWriter);
      },
    });

    // Convert the <helfiQuoteFooterCite> model into an editable <cite> widget.
    conversion.for('editingDowncast').elementToElement({
      model: 'helfiQuoteFooterCite',
      view: (modelElement, { writer: viewWriter }) => {
        const cite = viewWriter.createEditableElement('cite', {});
        return toWidgetEditable(cite, viewWriter);
      },
    });
  }

}
