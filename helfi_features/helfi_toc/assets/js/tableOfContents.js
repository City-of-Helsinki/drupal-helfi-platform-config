'use strict';

(function ($, Drupal) {
  Drupal.behaviors.table_of_contents = {
    attach: function attach() {

      function findAvailableId(name, reserved, anchors, count) {
        var newName = name;
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

      var anchors = [];
      var tableOfContents = $('#helfi-toc-table-of-contents');
      var tableOfContentsList = $('#helfi-toc-table-of-contents-list > ul');
      var mainContent = $('main.layout-main-wrapper');
      var reservedElems = document.querySelectorAll('[id]');
      var reserved = []; // Lets list current id's here to avoid creating duplicates
      reservedElems.forEach(function (elem) {
        reserved.push(elem.id);
      });

      // Do not include sidebar H2, Table of contents H2 or cookie compliance warnings.
      var exclusions = '' +
        ':not(aside *)' +
        ':not(.unit__sidebar *)' +
        ':not(.service__sidebar *)' +
        ':not(#helfi-toc-table-of-contents *)' +
        ':not(.embedded-content-cookie-compliance *)' +
        ':not(.handorgel__header)'; // Accordion headings get their id's overridden by handorgel script

      var titleComponents = [
        'h2'+exclusions,
        'h3'+exclusions,
        'h4'+exclusions,
        'h5'+exclusions,
        'h6'+exclusions,
        '.handorgel__header > button', // Instead of targeting accordion headings, lets target the button inside them.
      ];

      // Craft table of contents.
      $(titleComponents.join(','), mainContent)
        .once()
        .each(function (index) {
          const name = this.textContent
            .toLowerCase()
            .trim()
            .replace(/ä/gi, 'a')
            .replace(/ö/gi, 'o')
            .replace(/å/gi, 'a')
            .replace(/\W/g, '-')
            .replace(/-(\d+)$/g, '_$1');

          let nodeName = this.nodeName.toLowerCase();
          if (nodeName === 'button') {
            nodeName = this.parentElement.nodeName.toLowerCase();
          }

          let anchorName;
          if (this.id) {
            anchorName = this.id;
          } else {
            anchorName = findAvailableId(name, reserved, anchors, 0);
          }
          anchors.push(anchorName);

          // Create table of contents if component is enabled.
          if (tableOfContentsList.length > 0 && nodeName === "h2") {
            tableOfContentsList.append(
              '<li class="table-of-contents__item"><a class="table-of-contents__link' +
              '" href="#' +
              anchorName +
              '">' +
              this.textContent.trim() +
              '</a></li>'
            );
          }
          // Create anchor links.
          $(this).attr('id', anchorName);
        });

      // Remove loading text.
      $('.js-remove', tableOfContents).remove();
    },
  };
})(jQuery, Drupal);
