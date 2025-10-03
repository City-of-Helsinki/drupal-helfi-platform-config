/**
 * Header anchor buttons
 *
 * Anchor buttons are small buttons next to selected headers that you can click
 * and get an anchor link copied to your clipboard. The functionality depends on the
 * header_id_injector.js.
 */

((Drupal, once) => {
  Drupal.HeaderAnchorButtons = {
    rgbToHex: (rgbColor) => {
      const result = rgbColor.match(/\d+/g);
      return result
        ? `#${(+result[0]).toString(16).padStart(2, '0')}${(+result[1]).toString(16).padStart(2, '0')}${(+result[2]).toString(16).padStart(2, '0')}`
        : null;
    },

    injectHeaderAnchorButtons: (content) => {

      // Attach a button to copy the anchor link to clipboard.
      const copiedAnchor = content.textContent.trim();

      // Create a span to hold the heading text to mitigate accessibility issues.
      const span = document.createElement('span');
      span.classList.add('header-text');

      // Collect all direct text nodes inside the header.
      const textNodes = Array.from(content.childNodes)
        .filter(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim().length > 0);

      // Move those text nodes into a span.
      textNodes.forEach(node => span.appendChild(node));

      // Insert the span at the beginning of the header.
      if (content.firstChild) {
        content.insertBefore(span, content.firstChild);
      } else {
        content.appendChild(span);
      }

      // Check the color of the header text so that titles with dark background
      // can have the button also colored white.
      const anchorStyle = window.getComputedStyle(content);

      const anchorLinkButton = document.createElement('button');
      anchorLinkButton.classList.add('header-anchor-button');

      if (Drupal.HeaderAnchorButtons.rgbToHex(anchorStyle.color) === '#ffffff') {
        anchorLinkButton.classList.add('header-anchor-button--white');
      }

      anchorLinkButton.setAttribute(
        'aria-label',
        Drupal.t('Copy link to header @name.', {'@name': copiedAnchor}, {context: 'Anchor link'})
      );

      // ARIA live region for the messages.
      const liveRegion = document.createElement('div');
      liveRegion.classList.add('header-anchor-button__live-region');
      liveRegion.classList.add('visually-hidden');
      liveRegion.setAttribute('aria-live', 'polite');
      liveRegion.setAttribute('aria-atomic', 'true');

      anchorLinkButton.addEventListener('click', () => {
        const url = `${window.location.origin}${window.location.pathname}#${content.id}`;
        navigator.clipboard.writeText(url)
          .then(() => {
            anchorLinkButton.classList.add('header-anchor-button--success');
            content.appendChild(liveRegion);
            liveRegion.textContent = Drupal.t(
              'Link to header @name copied.',
              { '@name': copiedAnchor },
              { context: 'Anchor link' }
            );
            setTimeout(() => content.removeChild(liveRegion), 7000);
            setTimeout(() => anchorLinkButton.classList.remove('header-anchor-button--success'), 3000);
          })
          .catch(err => {
            anchorLinkButton.classList.add('header-anchor-button--error');
            content.appendChild(liveRegion);
            liveRegion.textContent = Drupal.t(
              'Failed to copy link header @name.',
              { '@name': copiedAnchor },
              { context: 'Anchor link' }
            );
            setTimeout(() => content.removeChild(liveRegion), 7000);
            setTimeout(() => anchorLinkButton.classList.remove('header-anchor-button--error'), 3000);
            console.error('Failed to copy:', err);
          });
      });

      const nodeName = content.nodeName.toLowerCase();

      if (nodeName === 'h2' && !content.hasAttribute('data-accordion-id')) {
        content.classList.add('js-header-with-anchor');
        content.appendChild(anchorLinkButton);
      }
    }
  };

  // Attach table of contents.
  Drupal.behaviors.headerAnchorButtons = {
    attach: function attach() {
      Drupal.HeaderIdInjector.injectedHeadings.forEach(({ content }) => {
        once('header-anchor-button', content).forEach(() => {
          Drupal.HeaderAnchorButtons.injectHeaderAnchorButtons(content);
        });
      });
    }
  };

})(Drupal, once);
