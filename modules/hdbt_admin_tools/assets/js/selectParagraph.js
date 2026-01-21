/**
 * @file Select paragraph.
 */
(function (Drupal, drupalSettings, once) {
  "use strict";

  Drupal.behaviors.selectParagraph = {
    attach: function (context) {

      const selects = once('select-paragraph-init', '.select-paragraph', context);

      if (selects.length > 0) {
        // Perform alterations for each "paragraph selection" select element.
        selects.forEach((select) => {
          const buttons = select.querySelectorAll('li.dropbutton__item');

          buttons.forEach(function(button) {
            const {
              paragraphTitle: title,
              paragraphDescription: description,
              paragraphImage: image,
            } = button.dataset;
            const { images } = drupalSettings.selectParagraph;

            const inputSubmit = button.querySelector('input[type="submit"]');

            let wrapper = inputSubmit.closest('.select-paragraph__wrapper');
            if (!wrapper) {
              // Only create wrapper if it doesn't exist.
              wrapper = document.createElement('div');
              wrapper.classList.add('select-paragraph__wrapper');
              inputSubmit.parentNode.insertBefore(wrapper, inputSubmit);
              wrapper.appendChild(inputSubmit);
            }

            if (typeof images !== 'undefined' && image in images) {
              const thumbnail = document.createElement('img');
              thumbnail.src = images[image];
              thumbnail.dataset.hoverTitle = title;
              thumbnail.dataset.hoverImage = images[image];
              thumbnail.dataset.hoverDescription = description;
              thumbnail.classList.add('select-paragraph__thumbnail');
              wrapper.insertBefore(thumbnail, wrapper.firstChild);
            }
          });
        });

        // Assign image preview to the paragraph selection.
        imagePreviewer('.select-paragraph .select-paragraph__thumbnail');
      }
    }
  }
})(Drupal, drupalSettings, once);
