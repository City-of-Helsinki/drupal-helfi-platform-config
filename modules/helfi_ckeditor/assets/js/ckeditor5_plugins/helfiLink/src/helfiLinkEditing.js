/**
 * @file A view to model and model to view converters for HelfiLink.
 */
import { Plugin } from 'ckeditor5/src/core';
import { Widget } from 'ckeditor5/src/widget';
import { findAttributeRange } from 'ckeditor5/src/typing';
import { isUrlExternal, parseProtocol } from './utils/utils';
import formElements from './formElements';

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
    editor.model.schema.extend('$text', { allowAttributes: modelName });

    // Convert attributes for downcast.
    // Model --> View (DOM / Data).
    editor.conversion.for('downcast').attributeToElement({
      model: modelName,
      view: (modelAttributeValue, { writer }) => {
        const attributeValues = {};

        // Create attribute values based on the type of view attributes types.
        if (modelAttributeValue && typeof viewAttribute === 'object') {
          attributeValues[Object.keys(viewAttribute)] = viewAttribute[Object.keys(viewAttribute)];
        } else {
          attributeValues[viewAttribute] = modelAttributeValue;
        }

        // Create the anchor element with the current attributes.
        const linkViewElement = writer.createAttributeElement('a', attributeValues, { priority: 5 });

        // Without it the isLinkElement() will not recognize the link
        // and the UI will not show up when the user clicks a link.
        writer.setCustomProperty('link', true, linkViewElement);

        return linkViewElement;
      },
    });

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
          value: (viewElement) => !!(viewElement.hasAttribute(viewAttributeKey) &&
            viewElement.getAttribute(viewAttributeKey) === viewAttributeValue)
        },
      });
    }
    else if (modelName === 'linkIsExternal' || modelName === 'linkProtocol') {
      editor.conversion.for('upcast').elementToAttribute({
        view: 'a',
        model: {
          key: modelName,
          value: viewElement => {
            // Check if the view element has an 'href' attribute.
            if (!viewElement.hasAttribute('href')) {
              return null; // No 'href' attribute, so return null.
            }

            // Get the 'href' attribute value.
            const url = viewElement.getAttribute('href');

            // Get whitelisted domains.
            const { whiteListedDomains } = this.editor.config.get('link');

            // Check if 'whiteListedDomains' is not defined or empty.
            if (!whiteListedDomains || !url) {
              return null;
            }

            const isExternal = isUrlExternal(url, whiteListedDomains);
            const protocol = parseProtocol(url);

            if (protocol && modelName === 'linkProtocol') {
              return protocol; // Return the scheme as 'linkProtocol'.
            }
            if (isExternal && modelName === 'linkIsExternal') {
              return true; // Return true for 'linkIsExternal'.
            }

            return null; // Return null for other cases.
          },
        },
        converterPriority: 'high', // Set the converter priority.
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
   * Remove attributes on unlink command execution.
   *
   * @param {string} modelName The model name.
   */
  _removeAttributeOnUnlinkCommandExecute(modelName) {
    const { editor } = this;
    const { model } = this.editor;
    const { selection } = model.document;
    const unlinkCommand = editor.commands.get('unlink');

    let isUnlinkingInProgress = false;

    // Make sure all changes are in a single undo step.
    // Cancel the original unlink first in the high priority.
    unlinkCommand.on('execute', evt => {
      if (isUnlinkingInProgress) {
        return;
      }

      evt.stop();

      // This single block wraps all changes that should be in a single undo step.
      model.change(() => {
        // Now, in this single "undo block" let the unlink command flow naturally.
        isUnlinkingInProgress = true;

        // Do the unlinking within a single undo step.
        editor.execute('unlink');

        // Let's make sure the next unlinking will also be handled.
        isUnlinkingInProgress = false;

        // The actual integration that removes the attribute.
        model.change(writer => {
          let ranges;

          // Get ranges from collapsed selection.
          if (selection.isCollapsed) {
            ranges = [ findAttributeRange(
              selection.getFirstPosition(),
              modelName,
              selection.getAttribute(modelName),
              model
            ) ];
          }
          // Get ranges from selected elements.
          else {
            ranges = model.schema.getValidRanges(selection.getRanges(), modelName);
          }

          // Remove the attribute from specified ranges.
          if (Array.isArray(ranges)) {
            ranges.forEach(range => writer.removeAttribute(modelName, range));
          }
        });
      });
    }, { priority: 'high' });
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
    const linkCommand = editor.commands.get('link');

    linkCommand.set(modelName, null);

    model.document.on('change', () => {
      linkCommand[modelName] = selection.getAttribute(modelName);
    });
  }

  /**
   * Helfi link button aka HDS-button converters.
   *
   * With these cast-converters we're able to convert CKEditor 4 markup...
   *
   * @code
   * <a
   *   href="#"
   *   class="hds-button hds-button--supplementary"
   *   data-design="hds-button hds-button--supplementary"
   *   data-link-text="Link text"
   *   data-protocol="http"
   *   data-is-external="true"
   *   data-selected-icon="download"
   * >
   *   <span class="hel-icon hel-icon--download" role="img" aria-hidden="true"></span>
   *   <span class="hds-button__label">Link text</span>
   * </a>
   * @endcode
   *
   * ...to this CKEditor5 markup:
   * @code
   * <a
   *   href="#"
   *   data-hds-icon-start="download"
   *   data-hds-variant="supplementary"
   *   data-hds-component="button"
   * >Link text</a>
   * @endcode
   *
   * The model will be the following. Check formElements for all defined
   * custom attribute models.
   * @code
   * <paragraph>
   *   <linkButton><linkHref><linkIcon><linkVariant> Link text
   * </paragraph>
   * @endcode
   */
  _defineHelfiButtonConverters() {
    const { editor } = this;

    // Allow link attributes in table cells.
    if (editor.model.schema.isRegistered('tableCell')) {
      editor.model.schema.extend('tableCell', { allowContentOf: '$block' });
    }

    /**
     * Convert the variant from button classes to a usable string.
     *
     * @param {string} classes Button classes as a string
     * @return {string|null} Return the variant as a string or null.
     */
    const convertVariants = (classes) => {
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
    };

    /**
     * Convert the icon from icon span classes to a usable string.
     *
     * @param {Iterable} items Icon span classes as a string
     * @return {string|false} Return the icon as a string or null.
     */
    const convertIcons = (items) => {
      let icon = false;
      let next = items.next();
      while (!next.done) {
        const item = next.value;
        if (item && item.startsWith('hel-icon--')) {
          icon = item.replace('hel-icon--', '');
          break;
        }
        next = items.next();
      }
      return icon;
    };

    // Remove obsolete <span> elements from the anchor tag.
    editor.conversion.for('upcast').elementToElement({
      view: { name: 'a' },
      model: (viewElement) => {
        const helfiButtonLabel = Array.from(viewElement.getChildren()).find(
          child =>
            child.name === 'span' &&
            child.hasClass('hds-button__label')
        );

        // Check if current anchor has a hds-button__label span and convert it
        // to simple text if it exists.
        if (helfiButtonLabel) {
          const anchorChildren = Array.from(viewElement.getChildren());
          const numOfChildren = anchorChildren.length;

          // Remove the span elements from anchor.
          if (numOfChildren > 0) {
            viewElement._removeChildren(0, numOfChildren);
          }

          // Convert possible icon span to data-hds-icon-start.
          anchorChildren.forEach(child => {
            if (child.name === 'span' && child.hasClass('hel-icon')) {
              const icon = convertIcons(child.getClassNames());
              if (icon) {
                viewElement._setAttribute('data-hds-icon-start', icon);
              }
            }
          });

          // Add the former span hds-button__label contents to anchor.
          Array.from(helfiButtonLabel.getChildren()).forEach(child => {
            viewElement._appendChild(child);
          });
        }

        // Check if there are obsolete <span> elements inside the anchor
        // and clear them as well.
        const orphanedSpan = Array.from(viewElement.getChildren()).find(
          element => {
            // Check only an existence of span elements.
            if (element.name && element.name === 'span') {

              // Let only language attributes pass,
              // otherwise return the element.
              if (
                element.getAttribute('dir') ||
                element.getAttribute('lang')
              ) {
                return false;
              }
              return element;
            }
            return false;
          }
        );

        // Remove the orphaned <span> and insert its children to the <a>.
        if (orphanedSpan) {
          viewElement._removeChildren(orphanedSpan.index, 1);
          Array.from(orphanedSpan.getChildren()).forEach(child => {
            viewElement._appendChild(child);
          });
        }

        // Remove obsolete data-protocol attributes.
        if (
          viewElement.hasAttribute('data-protocol') &&
          viewElement.getAttribute('data-protocol').startsWith('http')
        ) {
          viewElement._removeAttribute('data-protocol');
        }
        return viewElement;
      },
      converterPriority: 'highest',
    });

    // Convert CKE4 data-design attribute to linkVariant model.
    editor.conversion.for('upcast').attributeToAttribute({
      view: {
        name: 'a',
        key: 'data-design'
      },
      model: {
        key: 'linkVariant',
        value: (viewElement) => {
          let match;

          // Trust classes instead of old data-design attribute.
          if (viewElement.hasClass('hds-button')) {
            match = convertVariants([...viewElement._classes].join(' '));
            if (!match) {
              match = convertVariants(viewElement.getAttribute('data-design'));
            }
          }

          // We don't need primary variant.
          if (match && match === 'primary') {
            match = null;
          }
          return match;
        }
      }
    });

    // Convert CKE4 data-design attribute to linkButton model.
    editor.conversion.for('upcast').attributeToAttribute({
      view: {
        name: 'a',
        key: 'data-design'
      },
      model: {
        key: 'linkButton',
        value: (viewElement) => {
          let match;

          // Trust classes instead of old data-design attribute.
          if (viewElement.hasClass('hds-button')) {
            match = convertVariants([...viewElement._classes].join(' '));
            if (!match) {
              match = convertVariants(viewElement.getAttribute('data-design'));
            }
          }
          return match ? 'button' : false;
        }
      }
    });

    // Convert CKE4 "hds-button" class attribute to linkButton model.
    editor.conversion.for('upcast').attributeToAttribute({
      view: {
        name: 'a',
        key: 'class',
        value: 'hds-button'
      },
      model: {
        key: 'linkButton',
        value: 'button',
      }
    });

    // Convert data-protocol attribute to linkProtocol model.
    editor.conversion.for('upcast').attributeToAttribute({
      view: {
        name: 'a',
        key: 'data-protocol'
      },
      model: {
        key: 'linkProtocol',
        value: (viewElement) => {
          // If protocol is http or https, remove it as we don't need them.
          const handleProtocol = (protocol) => (
            protocol === 'https' || protocol === 'http'
          ) ? false : protocol;
          return handleProtocol(viewElement.getAttribute('data-protocol'));
        }
      },
      converterPriority: 'highest',
    });

    // Convert data-selected-icon attribute to linkIcon model.
    editor.conversion.for('upcast').attributeToAttribute({
      view: {
        name: 'a',
        key: 'data-selected-icon',
      },
      model: {
        key: 'linkIcon',
      }
    });
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
            let range = ranges.next();

            while (!range.done) {
              const currentRange = range.value;

              if (attributeValues[modelName]) {
                writer.setAttribute(modelName, attributeValues[modelName], currentRange);
              } else {
                writer.removeAttribute(modelName, currentRange);
              }
              range = ranges.next();
            }
          }
        });
      });
    }, { priority: 'high' });
  }
}
