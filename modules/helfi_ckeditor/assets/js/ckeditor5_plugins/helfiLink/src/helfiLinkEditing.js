/**
 * @file A view to model and model to view converters for HelfiLink.
 */
import {Plugin} from 'ckeditor5/src/core';
import {toWidgetEditable, Widget} from 'ckeditor5/src/widget';
import {findAttributeRange} from 'ckeditor5/src/typing';
import {formElements} from './formElements';

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

    // Define conversions from old link button markup to new button markup.
    this._defineHelfiButtonConverters();

    // Add attributes to linkCommand during its execution.
    this._addAttributeOnLinkCommandExecute(Object.keys(formElements));
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
  _defineHelfiButtonConverters() {
    const {editor} = this;

    // Allow link attributes in table cells.
    editor.model.schema.extend( 'tableCell', { allowContentOf: '$block' } );

    // The variants for different scenarios where a link has a following markup:
    // <a><span class="hds-button__label>{text}</span></a>
    const helfiButtonLabelSpanVariants = {
      'data-design': {
        name: 'a',
        attributes: {
          'data-design': true,
        }
      },
      'data-variant': {
        name: 'a',
        attributes: {
          'data-variant': true,
        }
      },
      'class-name': {
        name: 'a',
        classes: [
          'hds-button'
        ],
      },
    };

    // Go through each scenario and remove the <span> element from the link.
    Object.keys(helfiButtonLabelSpanVariants).forEach(variant => {
      const upcastView = helfiButtonLabelSpanVariants[variant];

      // Remove the <span class="hds-button__label"> element from the link
      // if it exists.
      editor.conversion.for('upcast').elementToElement({
        view: upcastView,
        model: (viewElement) => {
          const helfiButtonLabel = Array.from(viewElement.getChildren()).find(
            child =>
              child.name === 'span' &&
              child.hasClass('hds-button__label')
          );

          if (!helfiButtonLabel) {
            return;
          }

          const numOfChildren = Array.from(viewElement.getChildren()).length;
          if (numOfChildren > 0) {
            viewElement._removeChildren(0, numOfChildren);
          }

          Array.from(helfiButtonLabel.getChildren()).forEach(child => {
            viewElement._appendChild(child);
          });
        },
        converterPriority: 'highest',
      });
    });

    // A helper object to map old link button data-attributes and new
    // link button data-attributes.
    const mapDataAttributes = {
      'data-design': 'data-variant',
      'data-protocol': 'data-protocol',
      'data-selected-icon': 'data-icon-start',
      'data-is-external': 'data-is-external'
    };

    // Go through each attribute and convert the attribute to a simplified
    // anchor element.
    Object.keys(mapDataAttributes).forEach(cke4Attr => {
      const { conversion, model } = this.editor;

      const cke5Attr = mapDataAttributes[cke4Attr];

      model.schema.extend( '$text', { allowAttributes: cke4Attr } );
      model.schema.extend( '$text', { allowAttributes: cke5Attr } );

      // Convert old data-attribute anchor attribute to matching model.
      editor.conversion.for( 'upcast' ).attributeToAttribute( {
        view: {
          name: 'a',
          key: cke4Attr
        },
        model: {
          key: cke5Attr,
          value: ( viewElement ) => {
            let match;

            if (viewElement.getAttribute(cke4Attr)) {
              match = viewElement.getAttribute(cke4Attr);
            }
            if (viewElement.getAttribute(cke5Attr)) {
              match = viewElement.getAttribute(cke5Attr);
            }
            if (cke4Attr === 'data-design') {
              match = this._convertVariants(match);
            }
            // We don't need this data-attribute here as it will be generated
            // by the helfi_link_converter filter plugin.
            if (cke4Attr === 'data-is-external') {
              return;
            }
            return match;
          }
        }
      } );

      // Convert new data-attribute anchor attribute to matching model.
      editor.conversion.for( 'upcast' ).attributeToAttribute( {
        view: {
          name: 'a',
          key: cke5Attr
        },
        model: {
          key: cke5Attr,
          value: ( viewElement ) => {
            let match;
            if (viewElement.getAttribute(cke5Attr)) {
              match = viewElement.getAttribute(cke5Attr);
            }
            return match;
          }
        }
      } );

      // Convert old data-attribute model to an anchor "attribute" element
      // in dataDowncast.
      conversion.for('dataDowncast').attributeToElement({
        model: cke4Attr,
        view: ( attributeValue, { writer } ) => {
          if (!attributeValue) {
            return undefined;
          }
          return writer.createAttributeElement( 'a', { [cke5Attr]: attributeValue }, { priority: 5 } );
        },
      });

      // Convert new data-attribute model to an anchor "attribute" element
      // in data downcast.
      conversion.for('dataDowncast').attributeToElement({
        model: cke5Attr,
        view: ( attributeValue, { writer } ) => {
          if (!attributeValue) {
            return undefined;
          }
          return writer.createAttributeElement( 'a', { [cke5Attr]: attributeValue }, { priority: 5 } );
        },
      });

      // Convert old data-attribute model to an anchor "attribute" element
      // in editing downcast.
      conversion.for('editingDowncast').attributeToElement({
        model: cke4Attr,
        view: ( attributeValue, { writer } ) => {
          return this._editingDowncast(cke5Attr, attributeValue, writer);
        },
      });

      // Convert new data-attribute model to an anchor "attribute" element
      // in editing downcast.
      conversion.for('editingDowncast').attributeToElement({
        model: cke5Attr,
        view: ( attributeValue, { writer } ) => {
          return this._editingDowncast(cke5Attr, attributeValue, writer);
        },
      });
    });
  }

  /**
   * Helper function for the data-attribute conversion.
   *
   * @param {string} attributeKey New data-attribute name.
   * @param {string} attributeValue New data-attribute value.
   * @param {writer} writer The downcast writer.
   * @return {editableElement} Returns an editableElement.
   */
  _editingDowncast(attributeKey, attributeValue, writer) {
    if (!attributeValue) {
      return undefined;
    }
    const attributeElement = writer.createAttributeElement( 'a', { [attributeKey]: attributeValue }, { priority: 5 } );
    return toWidgetEditable( attributeElement, writer, { label: Drupal.t('Edit link') } );
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

        // Determine the selection range and add/remove the attributes to the
        // node or range.
        modelNames.forEach(modelName => {
          if (selection.isCollapsed) {
            // Get the current selection textNode or the nodeBefore the selection.
            // If neither are available, create a range from root position.
            const writtenRange = (position) => {
              const node = position.textNode || position.nodeBefore;
              if (!node) {
                const range = writer.createRange(position);
                writer.setSelection(range);
                return range;
              }
              return writer.createRangeOn(node);
            };

            // Set or remove attributes.
            if (attributeValues[modelName]) {
              writer.setAttribute(modelName, attributeValues[modelName], writtenRange(selection.getFirstPosition()));
            } else {
              writer.removeAttribute(modelName, writtenRange(selection.getFirstPosition()));
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
   * Convert the variant from button classes to a usable string.
   *
   * @param {string} classes Button classes as a string
   * @return {string|null} Return the variant as a string or null.
   */
  _convertVariants(classes) {
    const parts = classes.split(' '); // Split the string by spaces
    const variantFound = parts.find(part => part.startsWith('hds-button--'));
    const hdsButtonFound = parts.find(part => part.endsWith('hds-button'));

    if (variantFound) {
      return variantFound.replace('hds-button--', '');
    }

    if (hdsButtonFound) {
      return 'primary';
    }

    return null;
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
