import HelfiLinkBaseView from './helfiLinkBaseView';

/**
 * The HelfiLink details view class.
 */
export default class HelfiLinkVariantView extends HelfiLinkBaseView {

  /**
   * Render function for the Tom Select library.
   *
   * @param {string} element The <select> element to which attach the
   * Tom Select functionality.
   * @param {object} options The selectListOptions from Form elements config.
   */
  renderTomSelect(element, options) {
    // Render the <select> element.
    if (!this.tomSelect && element) {
      // The template for the Tom Select options and selected items.
      const renderTemplate = (item, escape) => {
        return `
          <span style="align-items: center; display: flex; height: 100%;">
            <span class="hel-icon--name" style="margin-left: 8px;">${escape(item.title)}</span>
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
        valueField: 'option',
        labelField: 'name',
        searchField: 'title',
        maxItems: 1,
        placeholder: this.options.label,
        options: Object.keys(options).map(option => ({
          option,
          title: options[option]
        })),
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

}
