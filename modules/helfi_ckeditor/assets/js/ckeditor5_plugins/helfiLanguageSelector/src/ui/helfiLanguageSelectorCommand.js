/**
 * @file defines HelfiLanguageSelectorCommand, which is executed when the
 * language selector toolbar button is pressed.
 */
import { Command } from 'ckeditor5/src/core';
import { stringifyLanguageAttribute } from '../utils/utils';

export default class HelfiLanguageSelectorCommand extends Command {

  /**
   * Executes the command. Applies the attribute to the selection or removes it from the selection.
   *
   * If `languageCode` is set to `false`, it will remove attributes.
   * Otherwise, the attribute will be added.
   *
   * @param {string} languageCode The language code to be applied to the model.
   * @param {string} textDirection The language text direction.
   */
  execute({ languageCode, textDirection }) {
    const { model } = this.editor;
    const doc = model.document;
    const { selection } = doc;
    const value = languageCode ? stringifyLanguageAttribute(languageCode, textDirection) : false;

    model.change(writer => {
      const firstPosition = selection.getFirstPosition();
      const node = firstPosition.textNode || firstPosition.nodeBefore;

      // If only the cursor is selected.
      if (selection.isCollapsed) {
        if (value) {
          // Write the value to selection.
          writer.setSelectionAttribute('helfiLanguageSelector', value);
        } else if (node) {
          // If there is no value, we should remove the helfiLanguageSelector
          // attributes. If there is node found, remove the attributes from the
          // whole node surrounding the selection.
          writer.removeAttribute('helfiLanguageSelector', writer.createRangeOn(node));
        } else {
          // Remove the helfiLanguageSelector attributes from current selection.
          writer.removeSelectionAttribute('helfiLanguageSelector');
        }
      // When there is a selection range selected.
      } else {
        const ranges = model.schema.getValidRanges(selection.getRanges(), 'helfiLanguageSelector');

        // eslint-disable-next-line no-restricted-syntax
        for (const range of ranges) {
          if (value) {
            // Write the value to selection.
            writer.setAttribute('helfiLanguageSelector', value, range);
          } else {
            // Remove the helfiLanguageSelector attributes from current
            // selection range.
            writer.removeAttribute('helfiLanguageSelector', range);
          }
        }
      }
    });
  }

  /**
   * @inheritDoc
   */
  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    this.value = this._getValueFromFirstAllowedNode();
    this.isEnabled = model.schema.checkAttributeInSelection(selection, 'helfiLanguageSelector');
  }

  /**
   * Returns the attribute value of the first node in the selection that allows the attribute.
   * For a collapsed selection it returns the selection attribute.
   *
   * @return {string|false} The attribute value or false.
   */
  _getValueFromFirstAllowedNode() {
    const { model } = this.editor;
    const { schema } = model;
    const { selection } = model.document;

    if (selection.isCollapsed) {
      return selection.getAttribute('helfiLanguageSelector') || false;
    }

    // eslint-disable-next-line no-restricted-syntax
    for (const range of selection.getRanges()) {
      // eslint-disable-next-line no-restricted-syntax
      for (const item of range.getItems()) {
        if (schema.checkAttribute(item, 'helfiLanguageSelector')) {
          return item.getAttribute('helfiLanguageSelector') || false;
        }
      }
    }

    return false;
  }
}
