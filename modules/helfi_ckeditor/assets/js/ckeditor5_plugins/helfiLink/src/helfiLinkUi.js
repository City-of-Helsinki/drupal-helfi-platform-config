/**
 * @file registers the HelfiLinkUi plugin and binds functionality to it.
 */

import { Plugin } from 'ckeditor5/src/core';
import {
  addListToDropdown,
  ContextualBalloon,
  createLabeledDropdown,
  createLabeledInputText,
  LabeledFieldView,
  Model,
} from 'ckeditor5/src/ui';

import { Collection } from 'ckeditor5/src/utils';
import HelfiCheckBoxView from './ui/helfiCheckBoxView';
import HelfiLinkProtocolView from './ui/helfiLinkProtocolView';
import { formElements } from './formElements';
import HelfiDetailsView from './ui/helfiDetailsView';

export default class HelfiLinkUi extends Plugin {

  constructor( editor ) {
    super( editor );
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
    const linkFormView = editor.plugins.get( 'LinkUI' ).formView;
    const contextualBalloon = editor.plugins.get(ContextualBalloon);

    // Act on when contextualBalloon (popup) and linkFormView is added.
    contextualBalloon._rotatorView.content.on( 'add', ( evt, view ) => {
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
    const linkFormView = editor.plugins.get( 'LinkUI' ).formView;
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
    const linkFormView = editor.plugins.get( 'LinkUI' ).formView;
    const linkProtocolView = new HelfiLinkProtocolView( this.editor.locale, createLabeledDropdown );
    const { urlInputView } = linkFormView;

    // Hide the Protocol field view by setting isVisible to false
    linkFormView.urlInputView.on('change:isEmpty', ( evt, name, value ) => {
      linkProtocolView.updateVisibility(value);
    } );

    // Define the dropdown items
    const dropdownItems = new Collection();

    // Assign the dropdown items.
    Object.keys(options).forEach(option => {
      dropdownItems.add({
        type: 'button',
        attributes: {
          class: [ 'helfi-link-form' ]
        },
        model: new Model({
          commandValue: options[option],
          label: options[option],
          withText: true,
          attributes: {
            class: [ 'ck', 'ck-link-protocol',  ],
            tabindex: '-1'
          },
        }),
        withText: true
      });
    });

    // Add the items to the dropdown
    addListToDropdown(linkProtocolView.fieldView, dropdownItems);

    // Set protocol as url input value.
    linkProtocolView.fieldView.on('execute', evt => {
      if (urlInputView.isEmpty && evt.source.commandValue) {
        urlInputView.fieldView.value = evt.source.commandValue;
      }
    });
    linkProtocolView.set('isVisible', true);

    return linkProtocolView;
  }

  /**
   * Create advanced settings (details/summary) view and handle the initial
   * state for it.
   *
   * @return {HelfiDetailsView} Returns the details view.
   */
  _createAndHandleAdvancedSettings() {
    const { editor } = this;
    const linkFormView = editor.plugins.get( 'LinkUI' ).formView;
    const advancedSettings = new HelfiDetailsView(editor.locale, this.advancedChildren);

    advancedSettings.set({
      label: Drupal.t('Advanced settings', {}, { context: 'CKEditor5 Helfi Link plugin' }),
      id: 'advanced-settings',
      isOpen: false,
    });

    // Add advanced settings (details summary) to linkFormView
    // after the linkHref field; 2.
    linkFormView.children.add( advancedSettings, 2 );

    // Handle the advanced settings open/close per contextualBalloon.
    editor.plugins.get( 'ContextualBalloon' )._rotatorView.content.on( 'add', ( evt, view ) => {
      if ( view !== linkFormView ) {
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
    } );

    return linkFormView.advancedSettings = advancedSettings;
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
    checkboxView.set( {
      isVisible: options.isVisible,
      tooltip: true,
      class: 'ck-find-checkboxes__box',
      id: options.machineName,
      label: options.label,
    } );

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
    const linkFormView = editor.plugins.get( 'LinkUI' ).formView;
    const options = this.formElements[modelName];
    let fieldView = {};

    // Create fields based on their types.
    switch (options.type) {
      case 'select':
        fieldView = this._createSelectList(modelName, options.selectListOptions);
        break;
      case 'checkbox':
        fieldView = this._createCheckbox(modelName);
        break;
      case 'static':
        // Do nothing for static group.
        fieldView = false;
        break;
      default:
        fieldView = new LabeledFieldView( editor.locale, createLabeledInputText );
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
      linkFormView.children.add( fieldView, options.type === 'select' ? 0 : 1 );
    }

    // Track the focus of the field elements.
    linkFormView.on( 'render', () => {
      linkFormView._focusables.add( fieldView, 1 );
      linkFormView.focusTracker.add( fieldView.element );
    } );

    return linkFormView[modelName] = fieldView;
  }

  /**
   * Handle form field submit.
   *
   * @param {object} models The models.
   */
  _handleFormFieldSubmit(models) {
    const { editor } = this;
    const { selection } = editor.model.document;
    const linkFormView = editor.plugins.get( 'LinkUI' ).formView;
    const linkCommand = editor.commands.get( 'link' );

    // Listen to linkFormView submit and inject form field values to
    // linkCommand arguments.
    this.listenTo( linkFormView, 'submit', (evt) => {
      const values = models.reduce((state, model) => {
        if (this.formElements[model].type === 'checkbox') {
          state[model] = linkFormView?.[model]?.checkboxInputView?.element?.checked;
        }
        else if (model === 'linkClass') {
          const options = this.formElements[model];
          state[model] = options.viewAttribute.class;
        }
        else {
          state[model] = linkFormView?.[model]?.fieldView?.element?.value ?? '';
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
        linkFormView.urlInputView.errorText = Drupal.t('When there is no selection, the link URL must be provided. To add a link without a URL, first select text in the editor and then proceed with adding the link.', {}, { context: 'CKEditor5 Helfi Link plugin' });
        evt.stop();
      }

      // Double-check if either of the checkbox values are checked and set both
      // to false accordingly.
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
      linkCommand.once( 'execute', ( evt, args ) => {
        if (args.length < 3) {
          args.push( values );
        } else if (args.length === 3) {
          Object.assign(args[2], values);
        } else {
          throw Error('The link command has more than 3 arguments.');
        }
      }, { priority: 'highest' } );
    }, { priority: 'high' } );
  }

  /**
   * Handle data loading into form field.
   *
   * @param {string} modelName The model name.
   */
  _handleDataLoadingIntoFormField(modelName) {
    const { editor } = this;
    const linkCommand = editor.commands.get( 'link' );
    const linkFormView = editor.plugins.get( 'LinkUI' ).formView;
    const options = this.formElements[modelName];

    // We don't need to handle data loading for linkProtocol nor static types.
    if (modelName === 'linkProtocol' || options.type === 'static') {
      return;
    }

    // Bind isChecked values of checkboxInputViews to the linkCommand.
    if (options.type === 'checkbox') {
      linkFormView[modelName].checkboxInputView.bind('isChecked').to(linkCommand, modelName);
    }
    // Bind field values of LabeledFieldViews to the linkCommand.
    else {
      linkFormView[modelName].fieldView.bind('value').to(linkCommand, modelName);
    }

    // This is a hack. This could be potentially improved by detecting when the
    // form is added by checking the collection of the ContextualBalloon plugin.
    editor.plugins.get( 'ContextualBalloon' )._rotatorView.content.on( 'add', ( evt, view ) => {
      if ( view !== linkFormView ) {
        return;
      }

      if (options.type === 'checkbox') {
        // Show the linkNewWindowConfirm checkbox if the
        // linkNewWindow checkbox is checked.
        if (modelName === 'linkNewWindowConfirm') {
          linkFormView[modelName]._updateVisibility(
            !!(linkFormView.linkNewWindow.checkboxInputView.isChecked)
          );
        }

        // If the checkbox is initially set to true, trigger the click event
        // for the linkFormView checkbox.
        if (linkCommand[modelName] && !linkFormView[modelName].checkboxInputView.element.checked) {
          linkFormView[modelName].checkboxInputView.element.click();
        }
      }
      else {
        // Note: Copy & pasted from LinkUI.
        // https://github.com/ckeditor/ckeditor5/blob/f0a093339631b774b2d3422e2a579e27be79bbeb/packages/ckeditor5-link/src/linkui.js#L333-L333
        linkFormView[modelName].fieldView.element.value = linkCommand[ modelName ] || '';
      }
    } );
  }

  /**
   * Handle link new window and link new window confirmation checkboxes
   * when user is checking/unchecking them.
   */
  _handleCheckboxes() {
    if (!this.formElements.linkNewWindowConfirm || !this.formElements.linkNewWindow) { return; }

    const { editor } = this;
    const linkFormView = editor.plugins.get( 'LinkUI' ).formView;

    // Handle linkNewWindowConfirm checkbox description.
    if (!linkFormView.linkNewWindowConfirm.element.description) {
      const description = document.createElement('div');
      description.innerHTML = this.formElements.linkNewWindowConfirm.description;
      description.classList.add('helfi-link-form__field_description');
      linkFormView.linkNewWindowConfirm.element.appendChild(description);
    }

    // Handle link new window and link new window confirmation checkbox linkages.
    linkFormView.linkNewWindow.on('change:isChecked', ( evt, name, value ) => {
      // Uncheck the link new window confirmation checkbox if the user unchecks
      // the link new window checkbox.
      if (!value) {
        // Trigger the change event by clicking the element.
        linkFormView.linkNewWindowConfirm.checkboxInputView.element.click();
      }

      // Update the link new window confirmation checkbox visibility based on
      // user actions on the link new window checkbox.
      linkFormView.linkNewWindowConfirm._updateVisibility(value);
    } );

    linkFormView.linkNewWindowConfirm.on('change:isChecked', ( evt, name, value ) => {
      // Uncheck the link new window checkbox if the use unchecks
      // the link new window confirmation checkbox.
      if (!value) {
        // Trigger the change event by clicking the element.
        linkFormView.linkNewWindow.checkboxInputView.element.click();
      }
    } );
  }

}
