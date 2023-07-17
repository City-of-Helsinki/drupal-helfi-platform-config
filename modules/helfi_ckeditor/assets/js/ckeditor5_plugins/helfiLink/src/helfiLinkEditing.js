/**
 * @file A view to model and model to view converters for HelfiLink.
 */
import { Plugin } from 'ckeditor5/src/core';
import { Widget } from 'ckeditor5/src/widget';
import { findAttributeRange } from 'ckeditor5/src/typing';
import { formElements } from './formElements';

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * This file has the logic for defining the HelfiLink model, and for how it is
 * converted to standard DOM markup.
 */
export default class HelfiLinkEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'HelfiLinkEditing';
  }

  init() {
    Object.keys(formElements).forEach(modelName => {
      if (!formElements[modelName].machineName) { return; }

      // Create conversions for model <-> view.
      this._convertAttribute(modelName, formElements[modelName].viewAttribute);

      // Remove attributes when unlink button is clicked.
      this._removeAttributeOnUnlinkCommandExecute(modelName);

      // Refresh attribute values.
      this._refreshAttributeValue(modelName);
    });

    // Add attributes to linkCommand during its execution.
    this._addAttributeOnLinkCommandExecute(Object.keys(formElements));
  }

  /**
   * Convert models and attributes between model <-> view.
   *
   * @param {string} modelName The model name.
   * @param {string|object} viewAttribute The view attribute name.
   */
  _convertAttribute(modelName, viewAttribute) {
    const { editor } = this;

    // Nothing to be done if there are no viewAttribute.
    if (!viewAttribute) { return; }

    // Add current model as an allowed attribute for '$text' nodes.
    editor.model.schema.extend( '$text', { allowAttributes: modelName } );

    // Convert attributes for downcast.
    // Model --> View (DOM / Data).
    editor.conversion.for( 'downcast' ).attributeToElement( {
      model: modelName,
      view: ( modelAttributeValue, { writer }) => {
        const attributeValues = {};

        // Create attribute values based on the type of view attributes types.
        if (modelAttributeValue && typeof viewAttribute === 'object') {
          attributeValues[Object.keys(viewAttribute)] = viewAttribute[Object.keys(viewAttribute)];
        } else {
          attributeValues[viewAttribute] = modelAttributeValue;
        }

        // Create the anchor element with the current attributes.
        const linkViewElement = writer.createAttributeElement('a', attributeValues,{ priority: 5 });

        // Without it the isLinkElement() will not recognize the link
        // and the UI will not show up when the user clicks a link.
        writer.setCustomProperty( 'link', true, linkViewElement );

        return linkViewElement;
      },
    } );

    // Handle upcast separately for attributes with object as their definitions.
    if (typeof viewAttribute === 'object') {
      const viewAttributeKey = Object.keys(viewAttribute)[0];
      const viewAttributeValue = viewAttribute[Object.keys(viewAttribute)];

      // Convert attributes for upcast.
      // View (DOM / Data) --> Model.
      this.editor.conversion.for('upcast').attributeToAttribute({
        view: {
          name: 'a',
          key: viewAttributeKey,
          value: viewAttributeValue,
        },
        model: {
          key: modelName,
          value: ( viewElement ) => !!(viewElement.hasAttribute(viewAttributeKey) &&
              viewElement.getAttribute(viewAttributeKey) === viewAttributeValue)
        },
      });
    }
    else {
      // View (DOM / Data) --> Model.
      editor.conversion.for('upcast').elementToAttribute({
        view: {
          name: 'a',
          attributes: {
            [ viewAttribute ]: true
          }
        },
        model: {
          key: modelName,
          value: viewElement => viewElement.getAttribute(viewAttribute)
        }
      });
    }
  }

  /**
   * Add attributes to linkCommand during its execution.
   *
   * @param {object} modelNames All model names.
   */
  _addAttributeOnLinkCommandExecute(modelNames) {
    const { editor } = this;
    const linkCommand = editor.commands.get('link');
    let linkCommandExecuting = false;

    linkCommand.on('execute', (evt, args) => {
      // Custom handling is only required if an attribute was passed
      // into editor.execute('link', ...).
      if (args.length < 3) {
        return;
      }
      if (linkCommandExecuting) {
        linkCommandExecuting = false;
        return;
      }

      // If the additional attribute was passed, we stop the default execution
      // of the LinkCommand. We're going to create Model#change() block for undo
      // and execute the LinkCommand together with setting the attribute.
      evt.stop();

      // Prevent infinite recursion by keeping records of when link command
      // is being executed by this function.
      linkCommandExecuting = true;
      const attributeValues = args[args.length - 1];
      const { model } = editor;
      const { selection } = model.document;

      // Wrapping the original command execution in a model.change() to make
      // sure there's a single undo step when the attribute is added.
      model.change(writer => {
        editor.execute('link', ...args);
        const firstPosition = selection.getFirstPosition();

        // Determine the selection range and add/remove the attributes to the
        // node or range.
        modelNames.forEach(modelName => {
          if (selection.isCollapsed) {
            const node = firstPosition.textNode || firstPosition.nodeBefore;

            if (attributeValues[modelName]) {
              writer.setAttribute(modelName, attributeValues[modelName], writer.createRangeOn(node));
            } else {
              writer.removeAttribute(modelName, writer.createRangeOn(node));
            }
            writer.removeSelectionAttribute(modelName);
          } else {
            const ranges = model.schema.getValidRanges(selection.getRanges(), modelName);

            for (const range of ranges) {
              if (attributeValues[modelName]) {
                writer.setAttribute(modelName, attributeValues[modelName], range);
              } else {
                writer.removeAttribute(modelName, range);
              }
            }
          }
        });
      } );
    }, { priority: 'high' } );
  }

  /**
   * Remove attributes on unlink command execution.
   *
   * @param {string} modelName The model name.
   */
  _removeAttributeOnUnlinkCommandExecute(modelName) {
    const { editor } = this;
    const { model } = this.editor;
    const { selection } = model.document;
    const unlinkCommand = editor.commands.get( 'unlink' );

    let isUnlinkingInProgress = false;

    // Make sure all changes are in a single undo step.
    // Cancel the original unlink first in the high priority.
    unlinkCommand.on( 'execute', evt => {
      if ( isUnlinkingInProgress ) {
        return;
      }

      evt.stop();

      // This single block wraps all changes that should be in a single undo step.
      model.change( () => {
        // Now, in this single "undo block" let the unlink command flow naturally.
        isUnlinkingInProgress = true;

        // Do the unlinking within a single undo step.
        editor.execute( 'unlink' );

        // Let's make sure the next unlinking will also be handled.
        isUnlinkingInProgress = false;

        // The actual integration that removes the attribute.
        model.change( writer => {
          let ranges;

          // Get ranges from collapsed selection.
          if ( selection.isCollapsed ) {
            ranges = [ findAttributeRange(
              selection.getFirstPosition(),
              modelName,
              selection.getAttribute( modelName ),
              model
            ) ];
          }
          // Get ranges from selected elements.
          else {
            ranges = model.schema.getValidRanges( selection.getRanges(), modelName );
          }

          // Remove the attribute from specified ranges.
          for ( const range of ranges ) {
            writer.removeAttribute( modelName, range );
          }
        } );
      } );
    }, { priority: 'high' } );
  }

  /**
   * Keep the attributes updated whenever editor model changes.
   *
   * @param {string} modelName The model name.
   */
  _refreshAttributeValue(modelName) {
    const { editor } = this;
    const { model } = this.editor;
    const { selection } = model.document;
    const linkCommand = editor.commands.get( 'link' );

    linkCommand.set( modelName, null );

    model.document.on( 'change', () => {
      linkCommand[ modelName ] = selection.getAttribute( modelName );
    } );
  }
}
