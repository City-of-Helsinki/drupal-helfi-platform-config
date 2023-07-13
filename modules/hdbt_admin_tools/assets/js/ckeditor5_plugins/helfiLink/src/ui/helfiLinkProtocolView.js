import { LabeledFieldView } from 'ckeditor5/src/ui';

/**
 * The HelfiLink details view class.
 */
export default class HelfiLinkProtocolView extends LabeledFieldView {
  /**
   * @inheritDoc
   */
  constructor(locale, fieldView) {
    super(locale, fieldView);

    // Initialize the isVisible property
    this.set('isVisible', true);

    // Add a CSS class to the view when isVisible is false
    this.bind('isVisible').to(this, 'updateVisibility');
  }

  // Method to update the visibility of the view based on the isVisible property
  updateVisibility(value) {
    if (value) {
      this.element.classList.remove('is-hidden');
    } else {
      this.element.classList.add('is-hidden');
    }
  }

}
