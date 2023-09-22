/**
 * @file registers the HelfiLinkUi plugin and binds functionality to it.
 */

import { Plugin } from 'ckeditor5/src/core';
import {
  ContextualBalloon,
  createLabeledInputText,
  LabeledFieldView,
} from 'ckeditor5/src/ui';

import { Collection } from 'ckeditor5/src/utils';
import HelfiCheckBoxView from './ui/helfiCheckBoxView';
import HelfiLinkProtocolView from './ui/helfiLinkProtocolView';
import formElements from './formElements';
import HelfiDetailsView from './ui/helfiDetailsView';
import HelfiLinkVariantView from './ui/helfiLinkVariantView';
import HelfiLinkIconView from './ui/helfiLinkIconView';

export default class HelfiLinkUi extends Plugin {

  constructor(editor) {
    super(editor);
    this.editor = editor;
    this.advancedChildren = new Collection();
    this.formElements = formElements;
  }

  init() {
    // Add a wrapper classes for the link form view.
    this._addContextualBalloonClass();

    // Add custom fields from formElements.
    const models = Object.keys(this.formElements).reverse();

    // Iterate through formElements and create fields accordingly.
    models.forEach(modelName => {
      // Skip form elements without machine names.
      if (!this.formElements[modelName].machineName) { return; }

      // Create form fields.
      const formField = this._createFormField(modelName);

      // Load existing data into form field.
      this._handleDataLoadingIntoFormField(modelName);

      // If form field is marked as part of advanced settings,
      // add it to advancedChildren object.
      if (
        this.formElements[modelName].group &&
        this.formElements[modelName].group === 'advanced'
      ) {
        this.advancedChildren.add(formField);
      }
    });

    // Move chosen fields under advanced settings.
    this._createAndHandleAdvancedSettings();

    // Add a descriptive text to URL input field.
    this._manipulateUrlInputField();

    // Add logic to checkboxes.
    this._handleCheckboxes();

    // Handle form field submit.
    this._handleFormFieldSubmit(models);
  }

  /**
   * Add a wrapper class to the contextual balloon panel when the LinkUI button
   * has been clicked.
   *
   * Note! This hacky way of removing and re-adding the link form view was the
   * only way to add class to its surrounding contextual balloon at the time
   * this plugin customization was being developed.
   */
  _addContextualBalloonClass() {
    const { editor } = this;
    const linkFormView = editor.plugins.get('LinkUI').formView;
    const contextualBalloon = editor.plugins.get(ContextualBalloon);

    // Act on when contextualBalloon (popup) and linkFormView is added.
    contextualBalloon._rotatorView.content.on('add', (evt, view) => {
      if (
        view !== linkFormView ||
        !contextualBalloon.hasView(linkFormView) ||
        contextualBalloon.view.element.classList.contains('helfi-contextual-balloon')
      ) {
        return;
      }

      contextualBalloon.remove(view);
      contextualBalloon.add({
        view: linkFormView,
        position: contextualBalloon._getBalloonPosition(),
        balloonClassName: 'helfi-contextual-balloon',
        withArrow: false,
      });
    });

    // Add custom classes for the LinkUI from view.
    linkFormView.extendTemplate({
      attributes: {
        class: [ 'helfi-link-form', 'ck-reset_all-excluded' ]
      }
    });
  }

  /**
   * Add a descriptive help text to URL input field.
   */
  _manipulateUrlInputField() {
    const { editor } = this;
    const linkFormView = editor.plugins.get('LinkUI').formView;
    const urlDescription = document.createElement('span');
    urlDescription.textContent = Drupal.t('Start typing to find content.', {}, { context: 'CKEditor5 Helfi Link plugin' });
    urlDescription.classList.add('helfi-link-form__field_description');
    linkFormView.urlInputView.element.appendChild(urlDescription);
  }

  /**
   * Create select list for protocol selection field.
   *
   * @param {string} modelName The model name.
   * @param {object} options The select list options.
   * @return {HelfiLinkProtocolView} Return the protocol view.
   */
  _createSelectList(modelName, options) {
    const { editor } = this;
    const linkFormView = editor.plugins.get('LinkUI').formView;
    let selectListView = {};

    switch (modelName) {
      case 'linkProtocol':
        selectListView = new HelfiLinkProtocolView(editor, options);

        // Hide the Protocol field view by setting isVisible to false
        linkFormView.urlInputView.on('change:isEmpty', (evt, name, value) => {
          selectListView.updateVisibility(value);
        });
        break;

      case 'linkVariant':
        selectListView = new HelfiLinkVariantView(editor, options);
        break;

      case 'linkIcon':
        selectListView = new HelfiLinkIconView(editor, options);

        // Hide the linkIcon if the design field is empty.
        linkFormView.linkVariant.on('change:isEmpty', (evt, name, value) => {
          selectListView.updateVisibility(value);
        });
        break;
      default:
        break;
    }

    // Apply configurations for the select list view.
    selectListView.set({
      isVisible: options.isVisible,
      id: options.machineName,
      label: options.label,
    });

    return selectListView;
  }

  /**
   * Create advanced settings (details/summary) view and handle the initial
   * state for it.
   *
   * @return {HelfiDetailsView} Returns the details view.
   */
  _createAndHandleAdvancedSettings() {
    const { editor } = this;
    const linkFormView = editor.plugins.get('LinkUI').formView;
    const advancedSettings = new HelfiDetailsView(editor.locale, this.advancedChildren);

    advancedSettings.set({
      label: Drupal.t('Advanced settings', {}, { context: 'CKEditor5 Helfi Link plugin' }),
      id: 'advanced-settings',
      isOpen: false,
    });

    // Add advanced settings (details summary) to linkFormView
    // after the linkHref field; 2.
    linkFormView.children.add(advancedSettings, 2);

    // Handle the advanced settings open/close per contextualBalloon.
    editor.plugins.get('ContextualBalloon')._rotatorView.content.on('add', (evt, view) => {
      if (view !== linkFormView) {
        return;
      }

      // The advanced settings (details summary element) is not bound to
      // any element. It is needed to close manually initially.
      if (linkFormView.advancedSettings) {
        linkFormView.advancedSettings.element.open = false;
        linkFormView.advancedSettings.detailsSummary.element.ariaExpanded = false;
        linkFormView.advancedSettings.detailsSummary.element.ariaPressed = false;
      }

      // Remove the error text if user has managed to make an error on last go.
      if (linkFormView.urlInputView.errorText) {
        linkFormView.urlInputView.errorText = '';
      }
    });

    linkFormView.advancedSettings = advancedSettings;
    return linkFormView.advancedSettings;
  }

  /**
   * Create checkboxes.
   *
   * @param {string} modelName The model name.
   * @return {HelfiCheckBoxView} Returns the checkbox view.
   */
  _createCheckbox(modelName) {
    const checkboxView = new HelfiCheckBoxView(this.editor.locale);
    const options = this.formElements[modelName];

    // Define the dropdown items
    checkboxView.set({
      isVisible: options.isVisible,
      tooltip: true,
      class: 'ck-find-checkboxes__box',
      id: options.machineName,
      label: options.label,
    });

    return checkboxView;
  }

  /**
   * Create form fields based on form elements.
   *
   * @param {string} modelName The model name.
   * @return {*} Returns current field view.
   */
  _createFormField(modelName) {
    const { editor } = this;
    const linkFormView = editor.plugins.get('LinkUI').formView;
    const options = this.formElements[modelName];
    let fieldView = {};

    // Create fields based on their types.
    switch (options.type) {
      case 'select':
        fieldView = this._createSelectList(modelName, options);
        break;
      case 'checkbox':
        fieldView = this._createCheckbox(modelName);
        break;
      case 'static':
        // Do nothing for static group.
        fieldView = false;
        break;
      default:
        fieldView = new LabeledFieldView(editor.locale, createLabeledInputText);
        break;
    }

    if (!fieldView) {
      return;
    }

    // Add basic information for the field.
    fieldView.machineName = modelName;
    fieldView.class = `helfi-link--${  options.machineName}`;
    fieldView.label = options.label;

    // Add help texts for the field.
    if (options.description) {
      fieldView.infoText = options.description;
    }

    // Handle advanced settings separately.
    if (!options.group || options.group !== 'advanced') {
      linkFormView.children.add(fieldView, options.type === 'select' ? 0 : 1);
    }

    // Track the focus of the field elements.
    linkFormView.on('render', () => {
      linkFormView._focusables.add(fieldView, 1);
      linkFormView.focusTracker.add(fieldView.element);
    });

    linkFormView[modelName] = fieldView;
    return linkFormView[modelName];
  }

  /**
   * Handle form field submit.
   *
   * @param {object} models The models.
   */
  _handleFormFieldSubmit(models) {
    const { editor } = this;
    const { selection } = editor.model.document;
    const linkFormView = editor.plugins.get('LinkUI').formView;
    const linkCommand = editor.commands.get('link');

    // Listen to linkFormView submit and inject form field values to
    // linkCommand arguments.
    this.listenTo(linkFormView, 'submit', (evt) => {
      // Check for Link URL existence.
      if (!linkFormView.urlInputView?.fieldView?.element?.value) {
        linkFormView.urlInputView.errorText = Drupal.t(
          'The link URL field cannot be empty.',
          {},
          { context: 'CKEditor5 Helfi Link plugin' }
        );
        evt.stop();
      }

      const values = models.reduce((state, model) => {
        switch (model) {
          case 'linkVariant': {
            const selectedValue = linkFormView?.[model]?.tomSelect.getValue();
            if (selectedValue && selectedValue !== 'link') {
              state[model] = selectedValue;
            }
            break;
          }

          case 'linkIcon':
            state[model] = linkFormView?.[model]?.tomSelect.getValue();
            break;

          default:
            state[model] = linkFormView?.[model]?.fieldView?.element?.value ?? '';
        }

        if (this.formElements[model].type === 'checkbox') {
          state[model] = linkFormView?.[model]?.checkboxInputView?.element?.checked;
        }

        return state;
      }, {});

      // Explain the link logic to user if they are trying to add link id to
      // a collapsed selection.
      if (
        selection.isCollapsed &&
        !linkFormView.urlInputView?.fieldView?.element?.value &&
        values.linkId
      ) {
        linkFormView.urlInputView.errorText = Drupal.t(
          'When there is no selection, the link URL must be provided. To add a link without a URL, first select text in the editor and then proceed with adding the link.',
          {},
          { context: 'CKEditor5 Helfi Link plugin' }
        );
        evt.stop();
      }

      // Double-check if either of the checkbox values is not checked and
      // set both to false accordingly.
      if (!values.linkNewWindowConfirm || !values.linkNewWindow) {
        values.linkNewWindowConfirm = false;
        values.linkNewWindow = false;

        // Trigger the change event by clicking the element.
        if (linkFormView.linkNewWindowConfirm.checkboxInputView.element.checked) {
          linkFormView.linkNewWindowConfirm.checkboxInputView.element.click();
        }
        // Trigger the change event by clicking the element.
        if (linkFormView.linkNewWindow.checkboxInputView.element.checked) {
          linkFormView.linkNewWindow.checkboxInputView.element.click();
        }
      }

      // Stop the execution of the link command caused by closing the form.
      // Inject the attribute values.
      linkCommand.once('execute', (execEvt, args) => {
        if (args.length < 3) {
          args.push(values);
        } else if (args.length === 3) {
          Object.assign(args[2], values);
        } else {
          throw Error('The link command has more than 3 arguments.');
        }
      }, { priority: 'highest' });
    }, { priority: 'high' });
  }

  /**
   * Handle data loading into form field.
   *
   * @param {string} modelName The model name.
   */
  _handleDataLoadingIntoFormField(modelName) {
    const { editor } = this;
    const linkCommand = editor.commands.get('link');
    const linkFormView = editor.plugins.get('LinkUI').formView;
    const options = this.formElements[modelName];

    switch (options.type) {
      // We don't need to handle data loading for static types.
      case 'static':
        return;

      // Bind isChecked values of checkboxInputViews to the linkCommand.
      case 'checkbox':
        linkFormView[modelName].checkboxInputView.bind('isChecked').to(linkCommand, modelName);
        break;

      // Bind field values of LabeledFieldViews to the linkCommand.
      case 'input':
        linkFormView[modelName].fieldView.bind('value').to(linkCommand, modelName);
        break;

      default:
        break;
    }

    // This is a hack. This could be potentially improved by detecting when the
    // form is added by checking the collection of the ContextualBalloon plugin.
    editor.plugins.get('ContextualBalloon')._rotatorView.content.on('add', (evt, view) => {
      if (view !== linkFormView) {
        return;
      }

      switch (options.type) {
        // Handle select lists.
        case 'select':
          // Initialize TomSelect for current select list.
          linkFormView[modelName].renderTomSelect(
            linkFormView[modelName].element,
            options?.selectListOptions
          );

          // Clear the selected values from the select list.
          linkFormView[modelName].tomSelect.clear();

          // Mark the default value as selected item.
          if (linkCommand[modelName]) {
            linkFormView[modelName].tomSelect.addItem(linkCommand[modelName], true);
          }

          // Add the protocol as URL input, if protocol has been selected.
          if (modelName === 'linkProtocol') {
            linkFormView[modelName].tomSelect.on('item_add', (selection) => {
              if (linkFormView.urlInputView.isEmpty) {
                linkFormView.urlInputView.fieldView.value = options.selectListOptions[selection];
                linkFormView.urlInputView.focus();
                linkFormView[modelName].tomSelect.clear();
              }
            });
          }

          // Add the default value for link variant.
          if (modelName === 'linkVariant') {
            linkFormView[modelName].tomSelect.on('item_add', (selection) => {
              linkFormView?.linkIcon.updateVisibility(selection !== 'link');

              if (selection === 'link') {
                linkFormView?.linkIcon.tomSelect.clear();
              }
            });
          }

          // Add the default value for link icon.
          if (modelName === 'linkIcon') {
            linkFormView[modelName].tomSelect.on('init', () => {
              linkFormView[modelName].updateVisibility(false);
            });
          }
          break;

        // Handle "link new window" checkboxes.
        case 'checkbox': {
          // Set the link new window checkbox initial value and the link new
          // window confirmation checkbox values based on the value
          // of the element's linkNewWindowConfirm model. The link new window
          // confirmation gets its value from <a target=_blank> attribute.
          const isChecked = !!(linkCommand.linkNewWindowConfirm);

          // Set initial value of current "link new window" and
          // "link new window confirmation" based on isChecked value.
          linkFormView[modelName].updateChecked(isChecked);
          linkFormView.linkNewWindowConfirm.updateVisibility(isChecked);
          break;
        }

        default:
          // Note: Copy & pasted from LinkUI.
          // https://github.com/ckeditor/ckeditor5/blob/f0a093339631b774b2d3422e2a579e27be79bbeb/packages/ckeditor5-link/src/linkui.js#L333-L333
          linkFormView[modelName].fieldView.element.value = linkCommand[modelName] || '';
      }
    });
  }

  /**
   * Handle link new window and link new window confirmation checkboxes
   * when user is checking/unchecking them.
   */
  _handleCheckboxes() {
    if (!this.formElements.linkNewWindowConfirm || !this.formElements.linkNewWindow) { return; }

    const { editor } = this;
    const linkFormView = editor.plugins.get('LinkUI').formView;

    // Handle linkNewWindowConfirm checkbox description.
    if (!linkFormView.linkNewWindowConfirm.element.description) {
      const description = document.createElement('div');
      description.innerHTML = this.formElements.linkNewWindowConfirm.description;
      description.classList.add('helfi-link-form__field_description');
      linkFormView.linkNewWindowConfirm.element.appendChild(description);
    }

    // Handle link new window and link new window confirmation checkbox linkages.
    linkFormView.linkNewWindow.on('change:isChecked', (evt, name, value) => {
      // Whenever the link new window checkbox is clicked, we want to ask
      // confirmation from the user. Uncheck the confirmation checkbox.
      linkFormView.linkNewWindowConfirm.updateChecked(false);

      // Update the "link new window confirmation" visibility based on the
      // value of "link new window" checkbox.
      linkFormView.linkNewWindowConfirm.updateVisibility(value);
    });
  }

}
