/**
 * Heading anchor buttons
 *
 * Anchor buttons are small buttons next to selected headings that you can click
 * and get an anchor link copied to your clipboard.
 *
 * The anchor ids are written server-side by HeadingIdInjector.php.
 */

((Drupal, once) => {
  Drupal.HeadingAnchorButtons = {
    // Content headings that have an anchor id to link to. Headings inside a
    // .hide-from-table-of-contents element are not part of the content.
    headingSelector: ['h2', 'h3', 'h4', 'h5', 'h6']
      .map((tag) => `${tag}[id]:not(.hide-from-table-of-contents *)`)
      .join(','),

    rgbToHex: (rgbColor) => {
      const result = rgbColor.match(/\d+/g);
      return result
        ? `#${(+result[0]).toString(16).padStart(2, '0')}${(+result[1]).toString(16).padStart(2, '0')}${(+result[2]).toString(16).padStart(2, '0')}`
        : null;
    },

    injectHeadingAnchorButtons: (content) => {
      // Attach a button to copy the anchor link to clipboard.
      const copiedAnchor = content.textContent.trim();

      // Create a span to hold the heading text to mitigate accessibility issues.
      const span = document.createElement('span');
      span.classList.add('heading-text');

      // Collect all direct text nodes inside the heading.
      const textNodes = Array.from(content.childNodes).filter(
        (node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim().length > 0,
      );

      // Move those text nodes into a span.
      // biome-ignore lint/suspicious/useIterableCallbackReturn: @todo UHF-12501
      textNodes.forEach((node) => span.appendChild(node));

      // Insert the span at the beginning of the heading.
      if (content.firstChild) {
        content.insertBefore(span, content.firstChild);
      } else {
        content.appendChild(span);
      }

      // Check the color of the heading text so that titles with dark background
      // can have the button also colored white.
      const anchorStyle = window.getComputedStyle(content);

      const anchorLinkButton = document.createElement('button');
      anchorLinkButton.classList.add('heading-anchor-button');

      if (Drupal.HeadingAnchorButtons.rgbToHex(anchorStyle.color) === '#ffffff') {
        anchorLinkButton.classList.add('heading-anchor-button--white');
      }

      anchorLinkButton.setAttribute(
        'aria-label',
        Drupal.t('Copy link to heading @name.', { '@name': copiedAnchor }, { context: 'Anchor link' }),
      );

      // ARIA live region for the messages.
      const liveRegion = document.createElement('div');
      liveRegion.classList.add('heading-anchor-button__live-region');
      liveRegion.classList.add('visually-hidden');
      liveRegion.setAttribute('aria-live', 'polite');
      liveRegion.setAttribute('aria-atomic', 'true');

      anchorLinkButton.addEventListener('click', () => {
        const url = `${window.location.origin}${window.location.pathname}#${content.id}`;
        navigator.clipboard
          .writeText(url)
          .then(() => {
            anchorLinkButton.classList.add('heading-anchor-button--success');
            content.appendChild(liveRegion);
            liveRegion.textContent = Drupal.t(
              'Link to heading @name copied.',
              { '@name': copiedAnchor },
              { context: 'Anchor link' },
            );
            setTimeout(() => content.removeChild(liveRegion), 7000);
            setTimeout(() => anchorLinkButton.classList.remove('heading-anchor-button--success'), 3000);
          })
          .catch((err) => {
            anchorLinkButton.classList.add('heading-anchor-button--error');
            content.appendChild(liveRegion);
            liveRegion.textContent = Drupal.t(
              'Failed to copy link heading @name.',
              { '@name': copiedAnchor },
              { context: 'Anchor link' },
            );
            setTimeout(() => content.removeChild(liveRegion), 7000);
            setTimeout(() => anchorLinkButton.classList.remove('heading-anchor-button--error'), 3000);
            console.error('Failed to copy:', err);
          });
      });

      const nodeName = content.nodeName.toLowerCase();

      if (nodeName === 'h2' && !content.hasAttribute('data-accordion-id')) {
        content.classList.add('js-heading-with-anchor');
        content.appendChild(anchorLinkButton);
      }
    },
  };

  // Attach the anchor buttons.
  Drupal.behaviors.headingAnchorButtons = {
    attach: function attach() {
      const mainContent = document.querySelector('main.layout-main-wrapper');

      if (!mainContent) {
        return;
      }

      once('heading-anchor-button', Drupal.HeadingAnchorButtons.headingSelector, mainContent).forEach((content) => {
        Drupal.HeadingAnchorButtons.injectHeadingAnchorButtons(content);
      });
    },
  };
})(Drupal, once);
