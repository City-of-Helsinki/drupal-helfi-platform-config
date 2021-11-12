'use strict';

(function ($, Drupal) {
  Drupal.behaviors.table_of_contents = {
    attach: function attach() {
      var anchorMap = {};
      var anchorLinks = [];
      var tableOfContents = $('#helfi-toc-table-of-contents');
      var tableOfContentsList = $('#helfi-toc-table-of-contents-list > ul');
      var mainContent = $('main.layout-main-wrapper');

      // Do not include sidebar H2 or Table of contents H2.
      var titleComponents = [
        'h2:not(aside *)' +
        ':not(.unit__sidebar *)' +
        ':not(.service__sidebar *)' +
        ':not(#helfi-toc-table-of-contents *)'
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

          let anchorName;
          if (!anchorMap[name]) {
            anchorName = name;
            anchorMap[name] = 2;
          } else {
            anchorName = name + '-' + anchorMap[name];
            anchorMap[name]++;
          }

          // Create table of contents if component is enabled.
          anchorLinks.push(this.textContent.trim());
          if (tableOfContentsList.length > 0) {
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
