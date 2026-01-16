/**
 * @file A view to model and model to view converters for helfiLanguageSelector.
 */
import { Plugin } from 'ckeditor5/src/core';
import { Widget } from 'ckeditor5/src/widget';
import { stringifyLanguageAttribute, parseLanguageAttribute, simplifyLangCode } from './utils/utils';
import HelfiLanguageSelectorCommand from './ui/helfiLanguageSelectorCommand';

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * This file has the logic for defining the helfiLanguageSelector model,
 * and for how it is converted to standard DOM markup.
 */
export default class HelfiLanguageSelectorEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'HelfiLanguageSelectorEditing';
  }

  constructor(editor) {
    super(editor);
    this.editor = editor;
    this.helfiLanguageSelectorConfig = this.editor.config.get('helfiLanguageSelector');
    this.currentLanguage = this.helfiLanguageSelectorConfig?.current_language;
  }

  /**
   * @inheritDoc
   */
  init() {
    const { editor } = this;
    const { conversion } = this.editor;

    // Add helfiLanguageSelector model as an allowed attribute for '$text' nodes.
    editor.model.schema.extend('$text', { allowAttributes: 'helfiLanguageSelector' });
    editor.model.schema.setAttributeProperties('helfiLanguageSelector', { copyOnEnter: true });

    // Define 'upcast' converter for helfiLanguageSelector lang attribute.
    conversion.for('upcast').elementToAttribute({
      model: {
        key: 'helfiLanguageSelector',
        value: (viewElement) => {
          const langAttr = viewElement.getAttribute('lang') ?? '';
          const languageCode = this._verifyLanguageCode(langAttr);

          // If the "lang" attribute does not exist, do not convert
          // the "lang" attribute nor the "dir" attribute.
          if (!languageCode) {
            return;
          }

          const textDirection = viewElement.getAttribute('dir') ?? '';
          return stringifyLanguageAttribute(languageCode.toLowerCase(), textDirection.toLowerCase());
        },
      },
      view: { name: 'span', attributes: { lang: /[\s\S]+/ } },
    });

    // Define 'upcast' converter for helfiLanguageSelector dir attribute.
    conversion.for('upcast').elementToAttribute({
      model: {
        key: 'helfiLanguageSelector',
        value: (viewElement) => {
          const langAttr = viewElement.getAttribute('lang');
          const languageCode = this._verifyLanguageCode(langAttr);

          // If the "lang" attribute does not exist, do not convert
          // the "dir" attribute.
          if (!languageCode) {
            return;
          }
          return viewElement.getAttribute('dir') ?? '';
        },
      },
      view: { name: 'span', attributes: { dir: /[\s\S]+/ } },
    });

    // Define 'downcast' converter for helfiLanguageSelector.
    conversion.for('downcast').attributeToElement({
      model: 'helfiLanguageSelector',
      view: (attributeValue, { writer }, data) => {
        if (!attributeValue) {
          return;
        }

        if (!data.item.is('$textProxy') && !data.item.is('documentSelection')) {
          return;
        }

        const { languageCode, textDirection } = parseLanguageAttribute(attributeValue);

        return writer.createAttributeElement('span', { lang: languageCode, dir: textDirection });
      },
    });

    // Add helfiLanguageSelectorCommand.
    editor.commands.add('helfiLanguageSelectorCommand', new HelfiLanguageSelectorCommand(editor));
  }

  /**
   * Verify the language code and return it if valid.
   *
   * @param {string} langAttribute The lang attribute.
   * @return {string|boolean} The language code or false.
   */
  _verifyLanguageCode(langAttribute) {
    // Return false if the lang attribute does not exist.
    if (!langAttribute) {
      return false;
    }

    // Remove possible country code from the language code.
    const languageCode = simplifyLangCode(langAttribute);

    // Remove lang and dir attributes if the lang attribute
    // does not exist.
    if (!languageCode) {
      return false;
    }

    // Remove lang and dir attributes if the lang attribute is the
    // same as the current language.
    if (languageCode.toLowerCase() === this.currentLanguage.toLowerCase()) {
      return false;
    }

    return languageCode;
  }
}
