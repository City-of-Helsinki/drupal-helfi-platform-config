/**
 * HDBT image previewer
 *
 * Generates a preview of an image when image is being hovered.
 * Needs to be called with selector and optional configuration.
 *
 * Example:
 *
 * HTML
 * <img src="images/test.jpg" data-hover-title="Test title" data-hover-image="test-big" class="thumbnail" />
 *
 * JS:
 * $(selector, context).imagePreviewer(selector, {
 *   fadeIn: 100,
 * });
 *
 */
(function (factory) {
  'use strict';
  if (typeof define === 'function' && define.amd) {
    define(['jquery'], factory);
  } else if (jQuery && !jQuery.fn.imagePreview) {
    factory(jQuery);
  }
})(function ($) {
  'use strict';

  // A helper function for randomising element ID.
  function randomID(prefix, length = 6) {
    let result = '';
    const characters = `ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789`;
    const charactersLength = characters.length;
    for (let i = 0; i < length; i++) {
      result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return prefix + '-' + result;
  }

  // Base configuration for the image previewer.
  let _config = {
    fadeIn: 200,
    fadeOut: 200,
    imageYOffset: 32,
    imageXOffset: 32,
  };

  $.fn.imagePreviewer = function (selector, configuration) {
    let config = $.extend(_config, configuration);
    const imageID = randomID('image-previewer', 10);

    // When selector is being hovered, show a preview of the selected image.
    $(document).on('mouseenter', selector, function(event) {
      event.stopImmediatePropagation();
      const image = $(this).data('hover-image');
      const title = $(this).data('hover-title') ?? '';
      const description = $(this).data('hover-description') ?? '';

      // Craft a new element for the preview image.
      $('body').append(`
        <p id="${imageID}" class="image-previewer__image-wrapper">
          <img class="image-previewer__image" width="723" height="407" src="${image}" alt="${title}" />
          <span class="image-previewer__title">${title}</span>
          <span class="image-previewer__description">${description}</span>
        </p>
      `);

      // Initialize the preview position.
      const imageTemplate = $(`#${imageID}`);
      imageTemplate
        .css('top',(event.pageY - config.imageYOffset) + 'px')
        .css('left',(event.pageX + config.imageXOffset) + 'px')
        .fadeIn(config.fadeIn);

    // When mouse is moved, move along with the cursor.
    }).on('mousemove', selector, function(event) {
      event.stopImmediatePropagation();
      let dp = $(`#${imageID}`);
      let height = dp.height();
      dp.css('top',(event.pageY - config.imageYOffset - height) + 'px')
        .css('left',(event.pageX + config.imageXOffset) + 'px');
    // When mouse leaves the thumbnail, remove the preview element.
    }).on('mouseleave', selector, function() {
      $(`#${imageID}`).fadeOut(config.fadeOut).remove();
    });

    // Remove the preview element if the user has managed to hover the preview
    // image during AJAX call.
    $(document).ajaxComplete(function() {
      $(`#${imageID}`).fadeOut(config.fadeOut).remove();
    });

    // Remove the preview element if user clicks on the thumbnail.
    $(window).on('select2:closing', function() {
      $('[id^="image-previewer-"]').each(function () {
        $(this).remove();
      })
    });
  };
});
