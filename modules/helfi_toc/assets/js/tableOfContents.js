((Drupal, once, drupalSettings) => {

  // Global table of contents object.
  Drupal.tableOfContents = {
    // A function to create table of content elements.
    createTableOfContentElements: (content) => {
      // Remove loading text and noscript element.
      let name = content.textContent
        .toLowerCase()
        .trim();

      // To ensure backwards compatibility, this is done only to "other" languages.
      if (!Drupal.HeaderIdInjector.mainLanguages().includes(drupalSettings.path.currentLanguage)) {
        Object.keys(Drupal.HeaderIdInjector.localeConversions()).forEach((swap) => {
          name = name.replace(new RegExp(Drupal.HeaderIdInjector.localeConversions()[swap], 'g'), swap);
        });
      }
      else {
        name = name
          .replace(/ä/gi, 'a')
          .replace(/ö/gi, 'o')
          .replace(/å/gi, 'a');
      }

      name = name
        // Replace any remaining non-word character including whitespace with '-'.
        // This leaves only characters matching [A-Za-z0-9-_] to the name.
        .replace(/\W/g, '-')
        // Use underscore at the end of the string: 'example-1' -> 'example_1'.
        .replace(/-(\d+)$/g, '_$1');

      let nodeName = content.nodeName.toLowerCase();
      if (nodeName === 'button') {
        nodeName = content.parentElement.nodeName.toLowerCase();
      }

      const anchorName = content.id
        ? content.id
        : Drupal.HeaderIdInjector.findAvailableId(name, 0);

      Drupal.HeaderIdInjector.anchors.push(anchorName);

      // Create anchor links.
      content.setAttribute('id', anchorName);
      content.setAttribute('tabindex', '-1');  // Set tabindex to -1 to avoid issues with screen readers.

      return {
        nodeName,
        anchorName,
      };
    },

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
      const mainContent = document.querySelector('main.layout-main-wrapper');
      const reservedElems = document.querySelectorAll('[id]');
      reservedElems.forEach(function(elem) {
        Drupal.HeaderIdInjector.reservedIds.push(elem.id);
      });

      if (Drupal.HeaderIdInjector.titleComponents()) {
        // Craft table of contents.
        once('table-of-contents', Drupal.HeaderIdInjector.titleComponents().join(','), mainContent)
          .forEach((content) => {

            const { nodeName, anchorName } = Drupal.tableOfContents.createTableOfContentElements(content, []);

            // Bail if table of contents is not enabled,
            // but retain the heading element id.
            if (!tableOfContents) {
              return;
            }

            // Create table of contents if component is enabled.
            if (tableOfContentsList && nodeName === 'h2') {
              const listItem = document.createElement('li');
              listItem.classList.add('table-of-contents__item');

              const link = document.createElement('a');
              link.classList.add('table-of-contents__link');
              link.href = `#${anchorName}`;
              link.textContent = content.textContent.trim();

              listItem.appendChild(link);
              tableOfContentsList.appendChild(listItem);
            }
          }
        );
      }

      if (tableOfContents) {
        Drupal.tableOfContents.updateTOC(tableOfContents);
      }
    },
  };
})(Drupal, once, drupalSettings);
