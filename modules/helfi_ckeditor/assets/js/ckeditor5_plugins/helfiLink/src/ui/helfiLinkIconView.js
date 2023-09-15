import HelfiLinkBaseView from './helfiLinkBaseView';

/**
 * The HelfiLink details view class.
 */
export default class HelfiLinkIconView extends HelfiLinkBaseView {

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

}
