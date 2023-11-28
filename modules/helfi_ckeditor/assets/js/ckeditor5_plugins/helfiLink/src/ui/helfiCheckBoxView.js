import { View } from 'ckeditor5/src/ui';
import { getCode } from 'ckeditor5/src/utils';

/**
 * The HelfiLink checkbox view class.
 */
export default class HelfiCheckBoxView extends View {
  /**
   * @inheritDoc
   */
  constructor(locale) {
    super(locale);
    const bind = this.bindTemplate;

    /**
     * (Optional) The additional CSS class set on the button.
     *
     * @observable
     * @member {String} #class
     */
    this.set('class');

    /**
     * Controls whether the checkbox view is visible. Visible by default, the checkboxes are hidden
     * using a CSS class.
     *
     * @observable
     * @default true
     * @member {Boolean} #isVisible
     */
    this.set('isVisible', true);

    /**
     * Indicates whether a related checkbox is checked.
     *
     * @observable
     * @default false
     * @member {Boolean} #isChecked
     */
    this.set('isChecked', false);

    /**
     * The text of the label associated with the checkbox view.
     *
     * @observable
     * @member {String} #label
     */
    this.set('label');

    /**
     * The text of the label associated with the checkbox view.
     *
     * @observable
     * @member {String} #label
     */
    this.set('description');

    /**
     * The HTML `id` attribute to be assigned to the checkbox.
     *
     * @observable
     * @default null
     * @member {String|null} #id
     */
    this.set('id', null);

    /**
     * (Optional) Controls the `tabindex` HTML attribute of the checkbox. By default, the checkbox is focusable
     * but is not included in the <kbd>Tab</kbd> order.
     *
     * @observable
     * @default -1
     * @member {String} #tabindex
     */
    this.set('tabindex', -1);

    /**
     * The collection of the child views inside of the checkbox {@link #element}.
     *
     * @readonly
     * @member {module:ui/viewcollection~ViewCollection}
     */
    this.children = this.createCollection();

    /**
     * The label of the checkbox view. It is configurable using the {@link #label label attribute}.
     *
     * @readonly
     * @member {module:ui/view~View} #labelView
     */
    this.labelView = this._createLabelView();

    /**
     * The input of the checkbox view.
     *
     * @readonly
     * @member {module:ui/view~View} #checkboxInputView
     */
    this.checkboxInputView = this._createCheckboxInputView();

    this.checkboxSpanToggle = this._createCheckboxSpanToggleView();

    // Bind isVisible to updateVisibility method.
    this.bind('isVisible').to(this, 'updateVisibility');

    // Bind isChecked to updateChecked method.
    this.bind('isChecked').to(this, 'updateChecked');

    this.setTemplate({
      tag: 'div',

      attributes: {
        class: [
          'form-type--checkbox',
          'helfi-link-checkbox',
          bind.if('isVisible', 'is-hidden', value => !value),
          bind.to('class'),
        ],
      },

      on: {
        keydown: bind.to(evt => {
          // Need to check target. Otherwise, we would handle space press on
          // input[type=text] and it would change checked property twice due
          // to default browser handling kicking in too.
          if (evt.target === this.element && evt.keyCode === getCode('space')) {
            this.isChecked = !this.isChecked;
          }
        }),
      },
      children: this.children
    });
  }

  /**
   * @inheritDoc
   */
  render() {
    super.render();

    this.children.add(this.checkboxInputView);
    this.children.add(this.checkboxSpanToggle);
    this.children.add(this.labelView);
  }

  /**
   * Focuses the {@link #element} of the checkbox.
   */
  focus() {
    this.element.focus();
  }

  /**
   * Creates a checkbox input view instance and binds it with checkbox attributes.
   *
   * @return {module:ui/view~View} Returns checkbox input view.
   */
  _createCheckboxInputView() {
    const checkboxInputView = new View();
    const bind = this.bindTemplate;

    checkboxInputView.setTemplate({
      tag: 'input',
      attributes: {
        type: 'checkbox',
        id: bind.to('id'),
        'checked': bind.if('isChecked'),
      },
      on: {
        change: bind.to(evt => {
          this.isChecked = evt.target.checked;
        })
      }
    });

    return checkboxInputView;
  }

  /**
   * Creates a checkbox toggle span.
   *
   * @return {module:ui/view~View} Returns checkbox span toggle view.
   */
  _createCheckboxSpanToggleView() {
    const checkboxSpanToggleView = new View();
    const bind = this.bindTemplate;

    /**
     * Markup:
     * <span class="checkbox-toggle">
     *   <span class="checkbox-toggle__inner"></span>
     * </span>
     */
    checkboxSpanToggleView.setTemplate({
      tag: 'span',
      attributes: {
        class: [
          'checkbox-toggle',
        ],
        id: bind.to('id'),
      },
      children: [
        {
          tag: 'span',
          attributes: {
            class: [
              'checkbox-toggle__inner'
            ],
          },
        },
      ],
    });

    return checkboxSpanToggleView;
  }

  /**
   * Creates a label view instance and binds it with checkbox attributes.
   *
   * @return {module:ui/view~View} Returns checkbox label view.
   */
  _createLabelView() {
    const labelView = new View();

    labelView.setTemplate({
      tag: 'label',

      attributes: {
        for: this.bindTemplate.to('id')
      },

      children: [
        {
          text: this.bindTemplate.to('label')
        }
      ]
    });

    return labelView;
  }

  /**
   * Update the visibility of the view based on the isVisible property
   *
   * @param {boolean} value The boolean value to be set to isVisible property.
   */
  updateVisibility(value) {
    if (value) {
      this.element.classList.remove('is-hidden');
    } else {
      this.element.classList.add('is-hidden');
    }
  }

  /**
   * Update the visibility of the view based on the isVisible property
   *
   * @param {boolean} value The boolean value to be set to isVisible property.
   */
  updateChecked(value) {
    if (value !== this.isChecked) {
      this.checkboxInputView?.element?.click();
    }
  }
}
