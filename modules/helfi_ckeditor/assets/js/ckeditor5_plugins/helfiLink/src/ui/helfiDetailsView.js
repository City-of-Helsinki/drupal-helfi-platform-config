import { View } from 'ckeditor5/src/ui';
import { getCode } from 'ckeditor5/src/utils';

/**
 * The HelfiLink details view class.
 */
export default class HelfiDetailsView extends View {
  /**
   * @inheritDoc
   */
  constructor(locale, children) {
    super(locale);
    this.advancedChildren = children;
    const bind = this.bindTemplate;

    /**
     * Controls whether the details view is enabled, i.e. it can be clicked and can execute an action.
     *
     * @observable
     * @default true
     * @member {Boolean} #isEnabled
     */
    this.set('isOpen', false);

    /**
     * The text of the label associated with the details view.
     *
     * @observable
     * @member {String} #label
     */
    this.set('label');

    /**
     * The HTML `id` attribute to be assigned to the details.
     *
     * @observable
     * @default null
     * @member {String|null} #id
     */
    this.set('id', null);

    /**
     * The collection of the child views inside the details {@link #element}.
     *
     * @readonly
     * @member {module:ui/viewcollection~ViewCollection}
     */
    this.children = this.createCollection();

    /**
     * The input of the details view.
     *
     * @readonly
     * @member {module:ui/view~View} #detailsInputView
     */
    this.detailsSummary = this._createDetailsSummary();

    this.setTemplate({
      tag: 'details',

      attributes: {
        id: bind.to('id'),
        class: [
          'ck-helfi-link-details',
          bind.if('isOpen', 'ck-is-open', isOpen => isOpen)
        ],
        open: bind.if('isOpen'),
      },

      on: {
        keydown: bind.to(evt => {
          // Need to check target. Otherwise, we would handle space press on
          // input[type=text] and it would change checked property
          // twice due to default browser handling kicking in too.
          if (evt.target === this.element && evt.keyCode === getCode('space')) {
            this.isOpen = !this.isOpen;
          }
        }),
      },
      children: this.children,
    });
  }

  /**
   * @inheritDoc
   */
  render() {
    super.render();

    this.children.add(this.detailsSummary);
    this.children.addMany(this.advancedChildren);
  }

  /**
   * Focuses the {@link #element} of the details.
   */
  focus() {
    this.element.focus();
  }

  _createDetailsSummary() {
    const detailsSummaryView = new View();

    detailsSummaryView.setTemplate({
      tag: 'summary',
      attributes: {
        role: 'button',
        class: [
          'ck-helfi-link-details__summary',
        ],
        'tabindex': 0,
      },
      children: [
        {
          text: this.bindTemplate.to('label')
        }
      ],
    });
    return detailsSummaryView;
  }

}
