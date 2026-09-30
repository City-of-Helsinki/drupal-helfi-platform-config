/**
 * @file Register the ways an editor can insert a non-breaking space.
 */

import { Plugin } from 'ckeditor5/src/core';
import { env } from 'ckeditor5/src/utils';

const NBSP = '\u00A0';

export default class HelfiNbspUi extends Plugin {
  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'HelfiNbspUi';
  }

  /**
   * Add the keystroke for inserting a non-breaking space.
   */
  init() {
    const { editor } = this;

    if (env.isMac) {
      return;
    }

    editor.keystrokes.set('Ctrl+Shift+Space', (_keyEvtData, cancel) => {
      editor.execute('insertText', { text: NBSP });
      cancel();
    });
  }

  /**
   * Add the non-breaking space to the "special characters" dialog.
   */
  afterInit() {
    const { editor } = this;

    if (!editor.plugins.has('SpecialCharacters')) {
      return;
    }

    const title = Drupal.t('Non-breaking space', {}, { context: 'CKEditor5 Helfi Nbsp plugin' });

    editor.plugins.get('SpecialCharacters').addItems('Text', [{ title, character: NBSP }]);
    this._addTileLabel(title);
  }

  /**
   * Add a label to the nbsp tile on "special characters" dialog.
   *
   * @param {string} title
   *   The title of the non-breaking space.
   */
  _addTileLabel(title) {
    const id = 'helfi-nbsp-tile';
    if (document.getElementById(id)) return;

    const selector = `.ck-character-grid__tile[title="${title.replace(/["\\]/g, '\\$&')}"] .ck-button__label`;
    const style = document.createElement('style');

    style.id = id;
    style.textContent = `${selector}{font-size:0}${selector}::after{content:'nbsp';font-size:10px}`;
    document.head.append(style);
  }
}
