/**
 * @file A view to model and model to view converters for helfiLanguageSelector.
 */
import { Plugin } from 'ckeditor5/src/core';
import { Widget } from 'ckeditor5/src/widget';
import { stringifyLanguageAttribute, parseLanguageAttribute } from './utils/utils';
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
    this.languageList = this.helfiLanguageSelectorConfig?.language_list ?? [];
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

    // Remove the unknown and the current languages from the spans.
    conversion.for('upcast').add((dispatcher) => {
      dispatcher.on(
        'element:span',
        (_event, data, conversionApi) => {
          const { viewItem } = data;
          const { consumable } = conversionApi;

          if (viewItem.hasAttribute('lang')) {
            // Take the text direction from the language list.
            if (viewItem.hasAttribute('dir')) {
              consumable.consume(viewItem, { attributes: ['dir'] });
            }

            if (!this._getLanguage(viewItem.getAttribute('lang'))) {
              consumable.consume(viewItem, { attributes: ['lang'] });
            }
          }

          // Unwrap the span when it has no attributes left.
          const hasAttributes = Array.from(viewItem.getAttributeKeys()).some((key) =>
            consumable.test(viewItem, { attributes: [key] }),
          );

          if (hasAttributes || !consumable.consume(viewItem, { name: true })) {
            return;
          }

          Object.assign(data, conversionApi.convertChildren(viewItem, data.modelCursor));
        },
        { priority: 'high' },
      );
    });

    // Define 'upcast' converter for helfiLanguageSelector lang attribute.
    conversion.for('upcast').elementToAttribute({
      model: {
        key: 'helfiLanguageSelector',
        value: (viewElement) => {
          const language = this._getLanguage(viewElement.getAttribute('lang'));

          if (!language) {
            return;
          }
          return stringifyLanguageAttribute(language.languageCode, language.textDirection);
        },
      },
      view: { name: 'span', attributes: { lang: /[\s\S]+/ } },
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
   * Find the language of the lang attribute from the language list.
   *
   * @param {string} langAttribute The lang attribute.
   * @return {object|null} The language or null if unknown or current.
   */
  _getLanguage(langAttribute) {
    const code = langAttribute?.trim().toLowerCase();

    if (!code) {
      return null;
    }

    // Match the full code first and then the code without the region.
    const [primary] = code.split('-');
    const language =
      this.languageList.find(({ languageCode }) => languageCode === code) ??
      this.languageList.find(({ languageCode }) => languageCode === primary);

    if (!language || language.languageCode === this.currentLanguage?.toLowerCase()) {
      return null;
    }
    return language;
  }
}
