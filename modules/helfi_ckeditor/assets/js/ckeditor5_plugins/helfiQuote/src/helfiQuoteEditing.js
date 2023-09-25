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
 * <blockquote data-helfi-quote>
 *   <p data-helfi-quote-text></p>
 *   <footer data-helfi-quote-author><cite></cite></footer>
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
    const { editor } = this;

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

    const { schema } = this.editor.model;

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
   * <blockquote data-helfi-quote>
   *   <p data-helfi-quote-text></p>
   *   <footer data-helfi-quote-author><cite></cite></footer>
   * </blockquote>
   */
  _defineConverters() {
    // Converters are registered via the central editor object.
    const { conversion } = this.editor;

    // Upcast Converters: determine how existing HTML is interpreted by the
    // editor. These trigger when an editor instance loads.
    const convertUpcast = (modelName, viewName, attribute = null) => {
      const variants = {
        'dataAttributes': {
          name: viewName,
          attributes: {
            [attribute]: '',
          }
        },
        'classes': {
          name: viewName,
          classes: [
            attribute,
          ]
        },
      };
      Object.keys(variants).forEach(variant => {
        const upcastView = variants[variant];
        conversion.for('upcast').elementToElement({
          view: upcastView,
          model: modelName,
        });
      });
    };

    // If <blockquote class="quote"> or <blockquote data-helfi-quote> is present
    // in the existing markup processed by CKEditor, then CKEditor recognizes
    // and loads it as a <helfiQuote> model.
    // Instances of <helfiQuote> are saved as
    // <blockquote data-helfi-quote>{{inner content}}</blockquote>.
    convertUpcast('helfiQuote', 'blockquote', 'quote');
    convertUpcast('helfiQuote', 'blockquote', 'data-helfi-quote');

    // If <p class="quote__text"> or <p data-helfi-quote-text> is present in
    // the existing markup processed by CKEditor, then CKEditor recognizes
    // and loads it as a <helfiQuoteText> model, provided it is a child element
    // of <helfiQuote>, as required by the schema.
    // Instances of <helfiQuoteText> are saved as
    // <p data-helfi-quote-text>{{inner content}}</p>.
    convertUpcast('helfiQuoteText', 'p', 'quote__text');
    convertUpcast('helfiQuoteText', 'p', 'data-helfi-quote-text');

    // If <footer class="quote__author"> or <footer data-helfi-quote-author>
    // is present in the existing markup processed by CKEditor, then CKEditor
    // recognizes and loads it as a <helfiQuoteFooter> model, provided it is
    // a child element of <helfiQuote>, as required by the schema.
    // Instances of <helfiQuoteFooter> are saved as
    // <footer data-helfi-quote-author>{{inner content}}</cite>.
    convertUpcast('helfiQuoteFooter', 'footer', 'quote__author');
    convertUpcast('helfiQuoteFooter', 'footer', 'data-helfi-quote-author');

    // If <cite> is present in the existing markup
    // processed by CKEditor, then CKEditor recognizes and loads it as a
    // <helfiQuoteFooterCite> model, provided it is a child element of
    // <helfiQuoteFooter>, as required by the schema.
    // Instances of <helfiQuoteFooterCite> are saved as
    // <cite>{{inner content}}</cite>.
    convertUpcast('helfiQuoteFooterCite', 'footer');

    // Downcast Converters: converts stored model data into HTML.
    const convertDowncast = (model, elementType, attributes = {}, container = false) => {
      const converterFunction = container ? 'createContainerElement' : 'createEditableElement';

      // These trigger when content is saved.
      conversion.for('dataDowncast').elementToElement({
        model,
        view: (modelElement, { writer: viewWriter }) =>
          viewWriter[converterFunction](elementType, attributes),
      });

      // Editing Downcast Converters. These render the content to the user for
      // editing, i.e. this determines what gets seen in the editor. These trigger
      // after the Data Upcast Converters, and are re-triggered any time there
      // are changes to any of the models' properties.
      conversion.for('editingDowncast').elementToElement({
        model,
        view: (modelElement, { writer: viewWriter }) => {
          const element = viewWriter[converterFunction](elementType, attributes);
          return container
            ? toWidget(element, viewWriter)
            : toWidgetEditable(element, viewWriter);
        },
      });
    };

    // Convert the <helfiQuote> model into a <blockquote> container element.
    convertDowncast('helfiQuote', 'blockquote', { 'data-helfi-quote': '' }, true);
    // Convert the <helfiQuoteText> model into an editable <p> element.
    convertDowncast('helfiQuoteText', 'p', { 'data-helfi-quote-text': '' });
    // Convert the <helfiQuoteFooter> model into a container <footer> element.
    convertDowncast('helfiQuoteFooter', 'footer', { 'data-helfi-quote-author': '' }, true);
    // Convert the <helfiQuoteFooterCite> model into an editable <cite> element.
    convertDowncast('helfiQuoteFooterCite', 'cite');
  }
}
