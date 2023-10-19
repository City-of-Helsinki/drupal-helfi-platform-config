/**
 * @file This is what CKEditor refers to as a master (glue) plugin. Its role is
 * just to load the “editing” and “UI” components of this Plugin. Those
 * components could be included in this file, but
 *
 * I.e, this file's purpose is to integrate all the separate parts of the plugin
 * before it's made discoverable via index.js.
 */

import { Plugin } from 'ckeditor5/src/core';
import { toWidget } from 'ckeditor5/src/widget';

export default class HelfiTable extends Plugin {
  static get pluginName() {
    return 'HelfiTableCaptionPlugin';
  }

  init() {
    const { editor } = this;
    const { conversion } = editor;
    const tableUtils = editor.plugins.get('TableUtils');

    // Override ckeditor5-table downcast converter for figure element.
    // The only difference of is the `tabindex` attribute.
    const downcastTable = (options = {}) => (table, { writer }) => {
      const headingRows = table.getAttribute('headingRows') || 0;
      const tableSections = [];

      // Table head slot.
      if (headingRows > 0) {
        tableSections.push(
          writer.createContainerElement('thead', null,
            writer.createSlot(element => element.is('element', 'tableRow') && element.index < headingRows)
          )
        );
      }

      // Table body slot.
      if (headingRows < tableUtils.getRows(table)) {
        tableSections.push(
          writer.createContainerElement('tbody', null,
            writer.createSlot(element => element.is('element', 'tableRow') && element.index >= headingRows)
          )
        );
      }

      // Figure element.
      const figureElement = writer.createContainerElement('figure', { class: 'table', 'tabindex': 0  }, [
        // Table with proper sections (thead, tbody).
        writer.createContainerElement('table', null, tableSections),

        // Slot for the rest (for example caption).
        writer.createSlot(element => !element.is('element', 'tableRow'))
      ]);

      const toTableWidget = (viewElement) => {
        writer.setCustomProperty('table', true, viewElement);
        return toWidget(viewElement, writer, { hasSelectionHandle: true });
      };
      return options.asWidget ? toTableWidget(figureElement) : figureElement;
    };

    conversion.for('editingDowncast').elementToStructure({
      model: {
        name: 'table',
        attributes: [ 'headingRows' ]
      },
      view: downcastTable({ asWidget: true }),
      converterPriority: 'high'
    });
    conversion.for('dataDowncast').elementToStructure({
      model: {
        name: 'table',
        attributes: [ 'headingRows' ]
      },
      view: downcastTable(),
      converterPriority: 'high'
    });
  }
}
