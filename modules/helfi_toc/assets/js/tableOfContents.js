/**
 * Table of Contents
 *
 * Functionality that compiles dynamically a list of h2-level headers
 * from the page that it is enabled to. Depends on the header_id_injector.js.
 */

((Drupal, once) => {
  // Global table of contents object.
  Drupal.tableOfContents = {
    // A function to reveal table of contents.
    updateTOC: (tocElement) => {
      // Remove loading text and noscript element.
      const removeElements = tocElement.parentElement.querySelectorAll('.js-remove');
      removeElements.forEach(function(element) {
        element.remove();
      });

      // Update toc visible.
      tocElement.setAttribute('data-js', 'true');
    },
  };

  // Attach table of contents.
  Drupal.behaviors.tableOfContents = {
    attach: function attach() {
      const tableOfContents = document.getElementById('helfi-toc-table-of-contents');
      const tableOfContentsList = document.querySelector('#helfi-toc-table-of-contents-list > ul');

      // Craft table of contents.
      if (tableOfContentsList && Drupal.HeaderIdInjector.injectedHeadings) {
        Drupal.HeaderIdInjector.injectedHeadings.forEach(({ nodeName, anchorName, content }) => {
          once('toc-builder', content).forEach(() => {
            if (nodeName === 'h2') {
              const listItem = document.createElement('li');
              listItem.classList.add('table-of-contents__item');

              const link = document.createElement('a');
              link.classList.add('table-of-contents__link');
              link.href = `#${anchorName}`;
              link.textContent = content.textContent.trim();

              listItem.appendChild(link);
              tableOfContentsList.appendChild(listItem);
            }
          });
        });
      }

      if (tableOfContents) {
        Drupal.tableOfContents.updateTOC(tableOfContents);
      }
    }
  };
})(Drupal, once);
