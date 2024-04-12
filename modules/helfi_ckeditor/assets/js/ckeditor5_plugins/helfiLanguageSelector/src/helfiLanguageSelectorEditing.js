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
    return [ Widget ];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'HelfiLanguageSelectorEditing';
  }

  /**
   * @inheritDoc
   */
  init() {
    const { editor } = this;
    const { conversion } = this.editor;

    // Add helfiLanguageSelector model as an allowed attribute for '$text' nodes.
    editor.model.schema.extend('$text', { allowAttributes: 'helfiLanguageSelector' });
    editor.model.schema.setAttributeProperties('helfiLanguageSelector', {
      copyOnEnter: true
    });

    // Define 'upcast' converter for helfiLanguageSelector.
    conversion.for('upcast').elementToAttribute({
      model: {
        key: 'helfiLanguageSelector',
        value: (viewElement) => {
          const languageCode = viewElement.getAttribute('lang') ?? '';
          const textDirection = viewElement.getAttribute('dir') ?? '';
          return stringifyLanguageAttribute(languageCode.toLowerCase(), textDirection.toLowerCase());
        }
      },
      view: {
        name: 'span',
        attributes: { lang: /[\s\S]+/ }
      }
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

        return writer.createAttributeElement('span', {
          lang: languageCode,
          dir: textDirection
        });
      }
    });

    // Add helfiLanguageSelectorCommand.
    editor.commands.add(
      'helfiLanguageSelectorCommand',
      new HelfiLanguageSelectorCommand(editor),
    );

  }

}
