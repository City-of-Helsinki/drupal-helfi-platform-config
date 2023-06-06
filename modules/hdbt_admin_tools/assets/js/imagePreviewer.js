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
 * imagePreviewer(selector, {
 *   fadeIn: 100,
 * });
 *
 */
(function () {
  'use strict';

  function randomID(prefix, length = 6) {
    let result = '';
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const charactersLength = characters.length;
    for (let i = 0; i < length; i++) {
      result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return prefix + '-' + result;
  }

  let _config = {
    fadeIn: 200,
    fadeOut: 200,
    imageYOffset: 32,
    imageXOffset: 32,
  };

  // Handle image preview event listeners.
  function imagePreviewHandler(selector, imageID, config) {
    return function(event) {
      if (!event.target.matches(selector)) return;
      // Rest of the code for handling the mouseenter event

      const image = event.target.dataset.hoverImage;
      const title = event.target.dataset.hoverTitle || '';
      const description = event.target.dataset.hoverDescription || '';

      let previewWrapper = document.createElement('p');
      previewWrapper.id = imageID;
      previewWrapper.className = 'image-previewer__image-wrapper';

      let img = document.createElement('img');
      img.className = 'image-previewer__image';
      img.width = 723;
      img.height = 407;
      img.src = image;
      img.alt = title;

      let titleSpan = document.createElement('span');
      titleSpan.className = 'image-previewer__title';
      titleSpan.textContent = title;

      let descriptionSpan = document.createElement('span');
      descriptionSpan.className = 'image-previewer__description';
      descriptionSpan.textContent = description;

      previewWrapper.appendChild(img);
      previewWrapper.appendChild(titleSpan);
      previewWrapper.appendChild(descriptionSpan);

      document.body.appendChild(previewWrapper);

      const imageTemplate = document.getElementById(imageID);
      imageTemplate.style.top = (event.pageY - config.imageYOffset) + 'px';
      imageTemplate.style.left = (event.pageX + config.imageXOffset) + 'px';

      function handleMouseMove(event) {
        event.stopImmediatePropagation();
        let dp = document.getElementById(imageID);
        let height = dp.offsetHeight;
        dp.style.top = (event.pageY - config.imageYOffset - height) + 'px';
        dp.style.left = (event.pageX + config.imageXOffset) + 'px';
      }

      function handleMouseLeave() {
        let element = document.getElementById(imageID);
        if (element) {
          element.style.display = 'none';
          element.parentNode.removeChild(element);
        }
      }

      event.target.addEventListener('mousemove', handleMouseMove);
      event.target.addEventListener('mouseleave', handleMouseLeave);
    }
  }

  function imagePreviewer(selector, configuration = {},  action = 'open') {
    let config = Object.assign({}, _config, configuration);
    const imageID = randomID('image-previewer', 10);

    const elements = document.querySelectorAll(selector);
    elements.forEach(function (element) {
      return action === 'open'
        ? element.addEventListener('mouseenter', imagePreviewHandler(selector, imageID, config))
        : element.removeEventListener('mouseenter', imagePreviewHandler(selector, imageID, config));
    });

    document.addEventListener('ajaxComplete', function () {
      let element = document.getElementById(imageID);
      if (element) {
        element.style.display = 'none';
        element.parentNode.removeChild(element);
      }
    });
  }

  window.imagePreviewer = imagePreviewer;
})();
