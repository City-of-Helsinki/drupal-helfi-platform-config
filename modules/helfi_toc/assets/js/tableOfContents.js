'use strict';

(function (Drupal, once) {
  Drupal.behaviors.table_of_contents = {
    attach: function attach() {

      function findAvailableId(name, reserved, anchors, count) {
        let newName = name;
        if (count > 0) { // Only when headings are not unique on page we want to add counter
          newName += '-' + count;
        }
        if (reserved.includes(newName)) {
          return findAvailableId(name, reserved, anchors, ++count);
        } else if (anchors.includes(newName)) {
          if (count === 0) {
            count++; // When reserved heading is visible on page, lets start counting from 2 instead of 1
          }
          return findAvailableId(name, reserved, anchors, ++count);
        }
        return newName;
      }

      const anchors = [];
      const tableOfContents = document.getElementById('helfi-toc-table-of-contents');
      const tableOfContentsList = document.querySelector('#helfi-toc-table-of-contents-list > ul');
      const mainContent = document.querySelector('main.layout-main-wrapper');
      const reservedElems = document.querySelectorAll('[id]');
      const reserved = []; // Let's list current id's here to avoid creating duplicates
      reservedElems.forEach(function (elem) {
        reserved.push(elem.id);
      });

      // Do not include sidebar H2, Table of contents H2 or cookie compliance warnings.
      const exclusions = '' +
        ':not(.layout-sidebar-first *)' +
        ':not(.layout-sidebar-second *)' +
        ':not(.tools__container *)' +
        ':not(.breadcrumb__container *)' +
        ':not(#helfi-toc-table-of-contents *)' +
        ':not(.embedded-content-cookie-compliance *)' +
        ':not(.react-and-share-cookie-compliance *)' +
        ':not(.handorgel__header)';

      const titleComponents = [
        'h2'+exclusions,
        'h3'+exclusions,
        'h4'+exclusions,
        'h5'+exclusions,
        'h6'+exclusions,
        '.handorgel__header > button',
      ];

      // Craft table of contents.
      once('table-of-contents', titleComponents.join(','), mainContent)
        .forEach(function (content) {
          const name = content.textContent
            .toLowerCase()
            .trim()
            .replace(/ä/gi, 'a')
            .replace(/ö/gi, 'o')
            .replace(/å/gi, 'a')
            .replace(/\W/g, '-')
            .replace(/-(\d+)$/g, '_$1');

          let nodeName = content.nodeName.toLowerCase();
          if (nodeName === 'button') {
            nodeName = content.parentElement.nodeName.toLowerCase();
          }

          const anchorName = content.id
            ? content.id
            : findAvailableId(name, reserved, anchors, 0);

          anchors.push(anchorName);

          // Create table of contents if component is enabled.
          if (tableOfContentsList && nodeName === "h2") {
            let listItem = document.createElement('li');
            listItem.classList.add('table-of-contents__item');

            let link = document.createElement('a');
            link.classList.add('table-of-contents__link');
            link.href = '#' + anchorName;
            link.textContent = content.textContent.trim();

            listItem.appendChild(link);
            tableOfContentsList.appendChild(listItem);
          }
          // Create anchor links.
          content.setAttribute('id', anchorName);
        });

      // Remove loading text.
      const removeElements = tableOfContents.querySelectorAll('.js-remove');
      removeElements.forEach(function (element) {
        element.remove();
      });
    },
  };
})(Drupal, once);
