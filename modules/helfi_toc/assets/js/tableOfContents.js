/**
 * Table of Contents
 *
 * This functionality creates an interactive table of contents by scanning
 * the page for H2 headings. It works alongside headingIdInjector.js which
 * ensures all headings have unique IDs.
 *
 * The table of contents is built automatically when the page loads
 * and updates based on the content.
 */
((Drupal, once) => {
  /**
   * Initialize the tableOfContents namespace if it doesn't exist.
   */
  Drupal.tableOfContents = Drupal.tableOfContents || {};

  /**
   * Retrieve all headings that were processed by HeadingIdInjector.
   * Each heading contains:
   * - nodeName: The HTML tag name ('H2')
   * - anchorName: The ID used for the anchor link
   * - content: Reference to the actual DOM element
   *
   * @returns {Array} Array of heading objects or empty array if not available
   */
  Drupal.tableOfContents.getInjectedHeadings = () => {
    if (
      !Drupal.HeadingIdInjector ||
      !Drupal.HeadingIdInjector.injectedHeadings
    ) {
      return [];
    }
    return Array.from(Drupal.HeadingIdInjector.injectedHeadings);
  };

  /**
   * Creates a standard list item for the table of contents.
   *
   * This is the default implementation that creates a simple list item
   * with a link. The link points to the heading's ID and displays the
   * heading's text.
   *
   * @param {Object} options
   * @param {HTMLElement} options.content - The heading element
   * @param {string} options.anchorName - The ID of the heading to link to
   *
   * @returns {HTMLLIElement} The created list item with a link
   */
  Drupal.tableOfContents.createDefaultListItem = ({ content, anchorName }) => {
    const listItem = document.createElement('li');
    listItem.classList.add('table-of-contents__item');

    // Create a link element for the list item.
    const link = document.createElement('a');
    link.classList.add('table-of-contents__link');
    link.href = `#${anchorName || content.id}`;
    link.textContent = content.textContent.trim();

    // Append the link to the list item.
    listItem.appendChild(link);

    return listItem;
  };

  /**
   * Core function that builds the table of contents from the provided headings.
   *
   * Handles validation of input parameters, uses either the provided or
   * default list item builder, processes each heading to create
   * table of contents entries and updates the visibility when complete.
   *
   * @param {Object} options
   * @param {HTMLElement} options.tocElement - The container for the TOC
   * @param {HTMLElement} options.tocListElement - The <ul> where items will be added
   * @param {Array} options.headings - Array of heading objects from getInjectedHeadings()
   * @param {Function} [options.createListItem] - Optional custom function to create list items
   */
  Drupal.tableOfContents.buildList = ({
    tocElement,
    tocListElement,
    headings,
    createListItem,
  }) => {
    // Validate input parameters and return if the parameters are invalid.
    if (!tocElement || !tocListElement || !headings || !headings.length) {
      return;
    }

    // Use the provided list item builder or the default one.
    const listItemBuilder =
      createListItem || Drupal.tableOfContents.createDefaultListItem;

    // Process each heading to create table of contents entries.
    headings.forEach(({ content, anchorName, nodeName }) => {
      if (!content) {
        return;
      }

      once('toc-builder', content).forEach(() => {
        const tagName = content.tagName || nodeName?.toUpperCase();

        // Only H2 headings are added to the table of contents..
        if (tagName !== 'H2') {
          return;
        }

        // Create the list item using the provided builder function.
        const listItem = listItemBuilder({ content, anchorName });

        // Add the list item to the table of contents.
        if (listItem) {
          tocListElement.appendChild(listItem);
        }
      });
    });

    // Update the table of contents visibility.
    Drupal.tableOfContents.updateTOC(tocElement);
  };

  /**
   * Updates the table of contents visibility and state after it's been built.
   *
   * Handles cleanup of loading placeholders, adds a data attribute to indicate
   * JavaScript is active, and ensures the table of contents is visible
   * after loading.
   *
   * @param {HTMLElement} tocElement - The TOC container element to update
   */
  Drupal.tableOfContents.updateTOC = (tocElement) => {
    if (!tocElement) {
      return;
    }

    tocElement.parentElement
      ?.querySelectorAll('.js-remove')
      .forEach((element) => {
        element.remove();
      });
    tocElement.setAttribute('data-js', 'true');
  };

  /**
   * Initialize the table of contents when the page loads.
   */
  Drupal.behaviors.tableOfContents = {
    attach(context) {
      // Only run once when the full document is loaded, not during AJAX updates
      // or if the table of contents has already been initialized.
      if (window.tableOfContentsInitialized && context === document) {
        return;
      }

      const tableOfContents = document.querySelector(
        '#helfi-toc-table-of-contents',
      );
      const tableOfContentsList = document.querySelector(
        '#helfi-toc-table-of-contents-list > ul',
      );

      // If either the TOC container or list element is missing, exit early.
      if (!tableOfContents || !tableOfContentsList) {
        return;
      }

      // Get all headings injected by HeadingIdInjector.
      const headings = Drupal.tableOfContents.getInjectedHeadings();
      if (!headings.length) {
        return;
      }

      // Initialize the TOC with the found headings.
      Drupal.tableOfContents.buildList({
        tocElement: tableOfContents,
        tocListElement: tableOfContentsList,
        headings,
      });

      // Set a flag to indicate that the table of contents has been initialized.
      window.tableOfContentsInitialized = true;
    },
  };
})(Drupal, once);
