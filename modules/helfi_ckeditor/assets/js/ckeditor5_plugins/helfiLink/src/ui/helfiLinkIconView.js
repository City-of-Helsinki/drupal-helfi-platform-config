import {View} from 'ckeditor5/src/ui';
import {getCode} from 'ckeditor5/src/utils';

/**
 * The HelfiLink details view class.
 */
export default class HelfiLinkIconView extends View {
  /**
   * @inheritDoc
   */
  constructor( editor, options ) {
    super( editor.locale );

    this.options = options;
    this.tomSelect = false;

    this.linkCommandConfig = editor.config.get('link');
    this.loadedIcons = this.linkCommandConfig?.loadedIcons;

    // Initialize the isVisible property
    this.set('isVisible', false);

    // Add a CSS class to the view when isVisible is false
    this.bind('isVisible').to(this, 'updateVisibility');

    const bind = this.bindTemplate;

    /**
     * Controls whether the details view is enabled, i.e. it can be clicked and can execute an action.
     *
     * @observable
     * @default true
     * @member {Boolean} #isEnabled
     */
    this.set( 'isOpen', false );

    /**
     * The text of the label associated with the details view.
     *
     * @observable
     * @member {String} #label
     */
    this.set( 'label' );

    /**
     * The HTML `id` attribute to be assigned to the details.
     *
     * @observable
     * @default null
     * @member {String|null} #id
     */
    this.set( 'id', null );

    /**
     * The collection of the child views inside the details {@link #element}.
     *
     * @readonly
     * @member {module:ui/viewcollection~ViewCollection}
     */
    this.setTemplate( {
      tag: 'select',

      attributes: {
        id: bind.to( 'id' ),
        class: [
          'ck-helfi-link-select-list',
        ],
        'data-placeholder': this.options.label,
        autocomplete: 'off',
      },
      on: {
        keydown: bind.to( evt => {
          // Need to check target. Otherwise, we would handle space press on
          // input[type=text] and it would change checked property
          // twice due to default browser handling kicking in too.
          if ( evt.target === this.element && evt.keyCode === getCode( 'space' ) ) {
            this.isOpen = !this.isOpen;
          }
        } ),
      },
    } );

  }

  /**
   * Update the visibility of the view based on isVisible property.
   *
   * @param {boolean} value Truthy value of visibility.
   */
  updateVisibility(value) {
    if (value) {
      this.tomSelect?.wrapper?.classList.remove('is-hidden');
      this.element.classList.remove('is-hidden');
    } else {
      this.tomSelect?.wrapper?.classList.add('is-hidden');
      this.element.classList.add('is-hidden');
    }
  }

  /**
   * Render function for the Tom Select library.
   *
   * @param {string} element The <select> element to which attach the
   * Tom Select functionality.
   */
  renderTomSelect(element) {
    // Render the <select> element.
    if (!this.tomSelect && element) {
      // The template for the Tom Select options and selected items.
      const renderTemplate = (item, escape) => {
        return `
          <span style="align-items: center; display: flex; height: 100%;">
            <span class="hel-icon hel-icon--${item.icon}" aria-hidden="true"></span>
            <span class="hel-icon--name" style="margin-left: 8px;">${escape(item.name)}</span>
          </span>
        `;
      };

      // Settings for the Tom Select.
      const settings = {
        plugins: {
          dropdown_input: {},
          remove_button: {
            title: 'Remove this item',
          }
        },
        valueField: 'icon',
        labelField: 'name',
        searchField: ['name'],
        options: Object.keys(this.loadedIcons).map(icon => ({
          icon,
          name: this.loadedIcons[icon]
        })),
        maxItems: 1,
        create: false,
        // Custom rendering functions for options and items
        render: {
          option: (item, escape) => renderTemplate(item, escape),
          item: (item, escape) => renderTemplate(item, escape),
        },
      };

      this.tomSelect = new TomSelect(this.element, settings);
    }
  }

  /**
   * @inheritDoc
   */
  render() {
    super.render();
  }

  /**
   * Focuses the {@link #element} of the details.
   */
  focus() {
    this.element.focus();
  }

}
