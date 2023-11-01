/**
 * @file registers the helfiLanguageSelectorUi plugin and binds functionality to it.
 */
import { Plugin } from 'ckeditor5/src/core';
import { createDropdown } from 'ckeditor5/src/ui';
import { Collection } from 'ckeditor5/src/utils';
import icon from '../../../../icons/helfiLanguageSelector.svg';
import LanguageSelectListView from './ui/languageSelectListView';
import { parseLanguageAttribute } from './utils/utils';
import translationWarmer from './utils/translationWarmer';

/**
 * Helper function for getting the current active language code.
 *
 * @param {string} languageAttribute The language attribute.
 * @return {*} Returns the language code if found.
 */
function getCommandValue(languageAttribute) {
  if (!languageAttribute) { return; }
  const { languageCode } = parseLanguageAttribute(languageAttribute);
  if (languageCode) {
    return languageCode;
  }
}

export default class HelfiLanguageSelectorUi extends Plugin {

  constructor(editor) {
    super(editor);
    this.editor = editor;
    this.advancedChildren = new Collection();
    this.helfiLanguageSelectorConfig = this.editor.config.get('helfiLanguageSelector');
    this.languageList = this.helfiLanguageSelectorConfig?.language_list;
    this.updateSelection = false;
    translationWarmer(editor.locale);
  }

  init() {
    const { editor } = this;
    const { t } = editor.locale;
    const removeTitle = t('Remove language from text');
    const defaultTitle = t('Select language');

    // Register the helfiLanguageSelector toolbar button.
    editor.ui.componentFactory.add('helfiLanguageSelector', (locale) => {

      // Create the dropdown view.
      const dropdownView = createDropdown(locale);

      // Create the toolbar button.
      dropdownView.buttonView.set({
        label: defaultTitle,
        icon,
        tooltip: true,
      });

      // Add class for the dropdown view.
      dropdownView.extendTemplate({
        attributes: {
          class: [ 'helfi-language-selector']
        }
      });

      // Add custom classes for the dropdown panel view.
      dropdownView.panelView.extendTemplate({
        attributes: {
          class: [
            'helfi-language-selector__dropdown-panel',
            'ck-reset_all-excluded',
          ]
        }
      });

      let selectListView;
      let tomSelect;
      const languageCommand = this.editor.commands.get('helfiLanguageSelectorCommand');

      dropdownView.on('change:isOpen', () => {

        if (tomSelect?.options) {
          // Set current language as the selected language in tomSelect.
          if (
            languageCommand.value &&
            !tomSelect.items.includes(getCommandValue(languageCommand.value))
          ) {
            tomSelect.setValue([ getCommandValue(languageCommand.value) ], true);
          }
          // Clear the selections in case there is no current language.
          else if (!languageCommand.value && tomSelect.items.length > 0) {
            tomSelect.clear();
          }
        }

        // No need to reinitialize the select list view.
        if (selectListView) {
          return;
        }

        // Create the select list view and add it to the dropdown panel view.
        selectListView = new LanguageSelectListView(locale, this.editor);
        dropdownView.panelView.children.add(selectListView);
        dropdownView.panelPosition = 'sw';

        // Delegate the execute command from selectListView to dropdownView.
        selectListView.delegate('execute').to(dropdownView);

        // The template for the Tom Select options and selected items.
        const renderTemplate = (item, escape) => `
          <span style="align-items: center; display: flex; height: 100%;">
            <span class="hel-icon--name" style="margin-left: 8px;">${escape(item.title)}</span>
          </span>
        `;

        // Settings for the Tom Select.
        const settings = {
          plugins: {
            remove_button: {
              title: removeTitle,
            }
          },
          valueField: 'languageCode',
          labelField: 'title',
          searchField: 'title',
          sortField: 'title',
          maxOptions: null,
          items: [ getCommandValue(languageCommand.value) ],
          options: [
            this.languageList.map((language) => ({ ...language, title: t(language.title) })),
          ],
          create: false,
          // Custom rendering functions for options and items
          render: {
            option: (item, escape) => renderTemplate(item, escape),
            item: (item, escape) => renderTemplate(item, escape),
          },
          // If the language selection has changed, execute the language command.
          onItemAdd: (languageCode) => {
            if (languageCommand.value !== languageCode) {
              languageCommand.execute({
                languageCode,
                textDirection: this.languageList.find(
                  (language) => language.languageCode === languageCode
                ).textDirection,
              });
              editor.editing.view.focus();
            }
          },
          // If the language selection has been removed,
          // execute the language command.
          onItemRemove: () =>  {
            if (languageCommand.value) {
              languageCommand.execute({
                languageCode: false,
              });
              editor.editing.view.focus();
            }
          },
        };

        /* global TomSelect */
        tomSelect = new TomSelect(selectListView.element, settings);
      });

      return dropdownView;
    });
  }

}
