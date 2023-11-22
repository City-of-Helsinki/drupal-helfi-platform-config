/**
 * @file registers the HelfiLinkUi plugin and binds functionality to it.
 */

import { Plugin } from 'ckeditor5/src/core';
import {
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
import { isUrlExternal, parseProtocol } from './utils/utils';

export default class HelfiLinkUi extends Plugin {

  constructor(editor) {
    super(editor);
    this.editor = editor;
    this.advancedChildren = new Collection();
    this.formElements = formElements;
    this.helfiContextualBalloonInitialized = false;
  }

  init() {
    // TRICKY: Work-around until the CKEditor team offers a better solution:
    // Force the ContextualBalloon to get instantiated early.
    // https://github.com/ckeditor/ckeditor5/commit/c276c45a934e4ad7c2a8ccd0bd9a01f6442d4cd3#diff-1753317a1a0b947ca8b66581b533616a5309f6d4236a527b9d21ba03e13a78d8.
    if (this.editor.plugins.get('LinkUI')._createViews) {
      this.editor.plugins.get('LinkUI')._createViews();
    }

    // Add a wrapper classes for the link form view.
    this._addContextualBalloonClass();

    // Copy the same solution from LinkUI as pointed out on
    // https://www.drupal.org/project/drupal/issues/3317769#comment-14985648 and
    // https://git.drupalcode.org/project/drupal/-/merge_requests/2909/diffs?commit_id=cc2cece3be1a9513b02a53d8a6862a6841ef4d5a.
    this.editor.plugins
      .get('ContextualBalloon')
      .on('change:visibleView', (evt, propertyName, newValue, oldValue) => {

        // Get the LinkUI form view.
        const linkFormView = this.editor.plugins.get('LinkUI').formView;

        // Check that we're handling the last linkFormView.
        // The 'set:visibleView' will trigger twice as we need to set
        // the contextual balloon classes in _addContextualBalloonClass().
        if (
          newValue === oldValue ||
          newValue !== linkFormView ||
          !this.helfiContextualBalloonInitialized
        ) {
          return;
        }

        // Add custom fields from formElements.
        const models = Object.keys(this.formElements).reverse();

        // Iterate through formElements and create fields accordingly.
        models.forEach(modelName => {
          // Skip form elements without machine names.
          if (!this.formElements[modelName].machineName) { return; }

          // Create form fields.
          const formField = this._createFormField(modelName, linkFormView);

          // If form field is marked as part of advanced settings,
          // add it to advancedChildren object.
          if (
            this.formElements[modelName].group &&
            this.formElements[modelName].group === 'advanced' &&
            typeof formField !== 'undefined'
          ) {
            this.advancedChildren.add(formField);
          }
        });

        // Move all fields with group:advanced setting to under advanced
        // settings accordion.
        this._createAdvancedSettingsAccordion(linkFormView);

        // Iterate through formElements and load data from LinkCommand plugin
        // to each form field. This needs to be run after the
        // _createAdvancedSettingsAccordion() as TomSelect library requires
        // rendered markup to attach itself to.
        models.forEach(modelName => {
          // Skip form elements without machine names.
          if (!this.formElements[modelName].machineName) { return; }

          // Load existing data into form field.
          this._handleDataLoadingIntoFormField(modelName, linkFormView);
        });

        // Add a descriptive text to URL input field.
        if (!linkFormView.urlInputView.infoText) {
          linkFormView.urlInputView.infoText = Drupal.t(
            'Start typing to find content.',
            {},
            { context: 'CKEditor5 Helfi Link plugin' }
          );
        }

        // Add logic to checkboxes.
        this._handleCheckboxes(linkFormView);

        // Handle form field submit.
        this._handleFormFieldSubmit(models);
    });
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

    // Copy the same solution from LinkUI as pointed out on
    // https://www.drupal.org/project/drupal/issues/3317769#comment-14985648 and
    // https://git.drupalcode.org/project/drupal/-/merge_requests/2909/diffs?commit_id=cc2cece3be1a9513b02a53d8a6862a6841ef4d5a.
    editor.plugins
      .get('ContextualBalloon')
      .on('set:visibleView', (evt, propertyName, newValue, oldValue) => {
        // Add a wrapper classes for the link form view.
        const linkFormView = editor.plugins.get('LinkUI').formView;
        if (newValue === oldValue || newValue !== linkFormView) {
          return;
        }

        const contextualBalloonPlugin = this.editor.plugins.get('ContextualBalloon');

        if (
          !contextualBalloonPlugin.hasView(linkFormView) ||
          contextualBalloonPlugin.view.element.classList.contains('helfi-contextual-balloon')
        ) {
          return;
        }

        linkFormView.template.attributes.class.push('helfi-link-form');
        linkFormView.template.attributes.class.push('ck-reset_all-excluded');

        // There should be an easier way to add classes to contextual balloon
        // plugin than removing and adding the view with custom settings.
        contextualBalloonPlugin.remove(newValue);
        contextualBalloonPlugin.add({
          view: linkFormView,
          position: contextualBalloonPlugin._getBalloonPosition(),
          balloonClassName: 'helfi-contextual-balloon',
          withArrow: false,
        });
        this.helfiContextualBalloonInitialized = true;
      });
  }

  /**
   * Create select list for protocol selection field.
   *
   * @param {string} modelName The model name.
   * @param {object} options The select list options.
   * @param {formView} linkFormView The link form view.
   * @return {HelfiLinkProtocolView} Return the protocol view.
   */
  _createSelectList(modelName, options, linkFormView) {
    const { editor } = this;
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
   * @param {formView} linkFormView The link form view.
   * @return {HelfiDetailsView} Returns the details view.
   */
  _createAdvancedSettingsAccordion(linkFormView) {
    // The advanced settings (details summary element) is not bound to
    // any element. It is needed to close manually initially.
    // Also return early if the advanced settings has already been created.
    if (linkFormView.advancedSettings) {
      linkFormView.advancedSettings.element.open = false;
      linkFormView.advancedSettings.detailsSummary.element.ariaExpanded = false;
      linkFormView.advancedSettings.detailsSummary.element.ariaPressed = false;
      return linkFormView.advancedSettings;
    }

    const { editor } = this;
    const advancedSettings = new HelfiDetailsView(editor.locale, this.advancedChildren);

    advancedSettings.set({
      label: Drupal.t('Advanced settings', {}, { context: 'CKEditor5 Helfi Link plugin' }),
      id: 'advanced-settings',
      isOpen: false,
    });

    // Add advanced settings (details summary) to linkFormView
    // after the linkHref field; 2.
    linkFormView.children.add(advancedSettings, 2);

    // Remove the error text if user has managed to make an error on last go.
    if (linkFormView.urlInputView.errorText) {
      linkFormView.urlInputView.errorText = '';
    }

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
   * @param {formView} linkFormView The link form view.
   * @return {*} Returns current field view.
   */
  _createFormField(modelName, linkFormView) {
    const { editor } = this;
    const options = this.formElements[modelName];
    const linkCommand = editor.commands.get('link');

    if (!linkFormView[modelName]) {
      let fieldView = {};

      // Create fields based on their types.
      switch (options.type) {
        case 'select':
          fieldView = this._createSelectList(modelName, options, linkFormView);
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
      fieldView.class = `helfi-link--${options.machineName}`;
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
      linkFormView._focusables.add(fieldView, 1);
      linkFormView.focusTracker.add(fieldView.element);
      linkFormView[modelName] = fieldView;

      // Bind isChecked values of checkboxInputViews to the linkCommand.
      if (options.type === 'checkbox') {
        linkFormView[modelName].checkboxInputView.bind('isChecked').to(linkCommand, modelName);
      }

      // Bind field values of LabeledFieldViews to the linkCommand.
      if (options.type === 'input') {
        linkFormView[modelName].fieldView.bind('value').to(linkCommand, modelName);
      }

      // Initially hide the linkProtocol field if the URL has been set.
      if (modelName === 'linkProtocol' && !linkFormView.urlInputView.isEmpty) {
        linkFormView[modelName].updateVisibility(false);
      }
      return linkFormView[modelName];
    }
  }

  /**
   * Handle data loading into form field.
   *
   * @param {string} modelName The model name.
   * @param {formView} linkFormView The link form view.
   */
  _handleDataLoadingIntoFormField(modelName, linkFormView) {
    const { editor } = this;
    const linkCommand = editor.commands.get('link');
    const options = this.formElements[modelName];

    if (typeof linkFormView[modelName] !== 'undefined') {
      switch (options.type) {
        // We don't need to handle data loading for static types.
        case 'static':
          return;

        // Bind isChecked values of checkboxInputViews to the linkCommand.
        case 'checkbox': {
          // Set the link new window checkbox initial value and the link new
          // window confirmation checkbox values based on the value
          // of the element's linkNewWindowConfirm model. The link new window
          // confirmation gets its value from <a target=_blank> attribute.
          const isChecked = !!(linkCommand.linkNewWindowConfirm);

          // Set initial value of current "link new window" and
          // "link new window confirmation" based on isChecked value.
          linkFormView[modelName].updateChecked(isChecked);
          if (linkFormView.linkNewWindowConfirm) {
            linkFormView.linkNewWindowConfirm.updateVisibility(isChecked);
          }
          break;
        }

        case 'select':
          // Initialize TomSelect for current select list.
          linkFormView[modelName].renderTomSelect(
            linkFormView[modelName].element,
            options?.selectListOptions
          );

          // Add the default value for link icon.
          if (modelName === 'linkIcon') {
            linkFormView[modelName].tomSelect.on('initialize', () => {
              linkFormView[modelName].updateVisibility(false);
            });
          }

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

            // It was decided to remove "primary" variable from anchor tag and
            // attach "primary" button styles to data-hds-component variable.
            // Add the "primary" as default value if
            // data-hds-component="button" is found.
            if (
              linkCommand.linkButton === 'button' &&
              (!linkCommand[modelName] || linkCommand[modelName] === 'primary')
            ) {
              linkFormView[modelName].tomSelect.addItem('primary', true);
            }

            // Hide the link icon form field if there is no variant selected.
            if (!linkCommand.linkVariant && linkCommand.linkButton !== 'button') {
              linkFormView?.linkIcon.updateVisibility(false);
            }

            // Hide the link icon form field if the variant is removed.
            linkFormView[modelName].tomSelect.on('item_remove', () => {
              linkFormView?.linkIcon.tomSelect.clear();
              linkFormView?.linkIcon.updateVisibility(false);
            });

            // Show the link icon form field if the variant is selected.
            linkFormView[modelName].tomSelect.on('item_add', (selection) => {
              linkFormView?.linkIcon.updateVisibility(selection !== 'link');

              if (selection === 'link') {
                linkFormView?.linkIcon.tomSelect.clear();
              }
            });
          }
          break;

        default:
          // Note: Copy & pasted from LinkUI.
          // https://github.com/ckeditor/ckeditor5/blob/f0a093339631b774b2d3422e2a579e27be79bbeb/packages/ckeditor5-link/src/linkui.js#L333-L333
          linkFormView[modelName].fieldView.element.value = linkCommand[modelName] || '';
      }
    }
  }

  /**
   * Handle link new window and link new window confirmation checkboxes
   * when user is checking/unchecking them.
   *
   * @param {formView} linkFormView The link form view.
   */
  _handleCheckboxes(linkFormView) {
    if (
      !this.formElements.linkNewWindowConfirm ||
      !this.formElements.linkNewWindow ||
      !linkFormView ||
      !linkFormView.linkNewWindow ||
      !linkFormView.linkNewWindowConfirm ||
      !linkFormView.linkNewWindowConfirm.element
    ) {
      return;
    }

    // Handle linkNewWindowConfirm checkbox description.
    if (!linkFormView.linkNewWindowConfirm.element.querySelector('.helfi-link-form__field_description')) {
      const description = document.createElement('div');
      description.innerHTML = this.formElements.linkNewWindowConfirm.description;
      description.classList.add('helfi-link-form__field_description');
      linkFormView.linkNewWindowConfirm.element.appendChild(description);
    }

    // Handle link new window and link new window confirmation checkbox linkages.
    linkFormView.linkNewWindow.on('change:isChecked', (evt, name, value) => {
      // Whenever the link new window checkbox is clicked, we want to ask
      // confirmation from the user. Uncheck the confirmation checkbox.
      linkFormView?.linkNewWindowConfirm.updateChecked(false);

      // Update the "link new window confirmation" visibility based on the
      // value of "link new window" checkbox.
      linkFormView?.linkNewWindowConfirm.updateVisibility(value);
    });
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

      // Get whitelisted domains.
      const { whiteListedDomains } = this.editor.config.get('link');

      // Get current href value of the link.
      const href = linkFormView.urlInputView?.fieldView?.element?.value;

      // Massage values for the link conversions.
      const values = models.reduce((state, model) => {
        switch (model) {
          case 'linkVariant': {
            const selectedValue = linkFormView?.[model]?.tomSelect.getValue();

            // Return current state if link design has not been selected or
            // link design is "link".
            if (!selectedValue || selectedValue === 'link') {
              return state;
            }

            // Set linkButton model variable if the link design is "primary".
            // Return the state as we don't want to print out
            // data-hds-variant="primary" to the anchor tag.
            if (selectedValue === 'primary') {
              state.linkButton = 'button';
              return state;
            }

            // Set current selection to the state and set "button" as the
            // linkButton model value.
            state[model] = selectedValue;
            state.linkButton = 'button';
            break;
          }

          case 'linkIcon':
            state[model] = linkFormView?.[model]?.tomSelect.getValue();
            break;

          case 'linkProtocol':
            if (!whiteListedDomains || !href) { break; }

            if (parseProtocol(href)) {
              state[model] = parseProtocol(href);
            }
            break;

          case 'linkIsExternal':
            if (!whiteListedDomains || !href) { break; }

            if (!parseProtocol(href) && isUrlExternal(href, whiteListedDomains)) {
              state[model] = isUrlExternal(href, whiteListedDomains);
              break;
            }
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

}
