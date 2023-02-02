/**
 * A plugin for hds-button.
 * Plugin for: http://ckeditor.com/license (GPL/LGPL/MPL: http://ckeditor.com/license)
 */

(function ($, Drupal, CKEDITOR) {

  /**
   * Get currently selected link.
   */
  function getCurrentLink(editor) {
    if (!editor.getSelection()) return null;

    const selected = editor.getSelection();
    const domElement = selected.getSelectedElement();

    if (domElement && domElement.is('a')) {
      return domElement;
    }

    const range = selected.getRanges(true)[0];

    if (range) {
      range.shrink(CKEDITOR.SHRINK_TEXT);
      return editor.elementPath(range.getCommonAncestor()).contains('a', 1);
    }

    return null;
  }

  /**
   * Handle label span.
   */
  function handleLabelSpan(editor, linkElement, action = 'add') {
    if (action === 'add') {
      let span = editor.document.createElement('span');
      span.setAttribute('class', 'hds-button__label');
      span.setHtml(linkElement.getHtml());
      linkElement.setHtml('');
      linkElement.append(span);
    }
    else {
      let spanLabel = linkElement.findOne('span.hds-button__label');
      if (spanLabel) {
        linkElement.setHtml(spanLabel.getHtml());
      }
    }
    editor.fire('saveSnapshot');
  }

  /**
   * Handle icon classes.
   */
  function handleClasses(editor, linkElement, elementClasses) {
    linkElement.$.classList = elementClasses;
    linkElement.$.dataset.ckeSavedClass = elementClasses;
    editor.fire('saveSnapshot');
  }

  /**
   * Integrates the hds-button plugin with the drupallink plugin.
   */
  function alterDrupallinkPlugin(editor) {
    // Nothing to integrate with if the drupallink plugin is not loaded.
    if (!editor.plugins.drupallink) {
      return;
    }

    // Register a linkable widget for drupallink: hds-button.
    CKEDITOR.plugins.drupallink.registerLinkableWidget('hds-button');

    // Act on ckeditor content change.
    editor.getCommand('drupallink').on('exec', function () {
      let linkElement = getCurrentLink(editor);

      // Act only if link element is being handled.
      if (!linkElement || !linkElement.$) {
        // Save selected text to global variable.
        window.drupalLinkTextSelection = editor.getSelectedHtml().$.textContent
          ? editor.getSelectedHtml().$.textContent
          : undefined;

        return;
      }

      // Check if link has link text and set it as data attribute.
      let text = linkElement.$.innerText;
      if (text) {
        linkElement.setAttribute('data-link-text', text);
      }
    });

    // Act on drupal dialog close.
    $(window).on('dialog:afterclose', function (e) {
      // Act only if editor instance is ready.
      if (editor.instanceReady) {
        let linkElement = getCurrentLink(editor);

        // Act only if link element is being handled.
        if (!linkElement || !linkElement.$) {
          return;
        }

        // A poor man's way to determine whether the link being handled is
        // new or already existing.
        const linkIsNew = !linkElement.getAttribute('data-cke-saved-href');

        // Clean unneeded "false" values from attributes.
        // F.e. data-is-external="false".
        for (const [key, value] of Object.entries(linkElement.getAttributes())) {
          if (value === 'false') {
            linkElement.removeAttribute(key);
          }
        }

        // Check for the button label.
        let buttonLabel = linkElement.find('span.hds-button__label');

        // Check if design has been selected (or exists) and act accordingly.
        if (linkElement.getAttribute('data-design')) {
          const design = linkElement.getAttribute('data-design');
          let classList = design;

          // Set design as data-attribute.
          linkElement.setAttribute('data-design', design);

          // Handle button designs.
          if (design !== 'link') {

            // Add button design to classList.
            classList = design;

            // Add button label if none exist.
            if (buttonLabel.count() === 0) {
              handleLabelSpan(editor, linkElement);
            }

            // Convert data-icon to data-selected-icon.
            // Icons are handled via selected-icon data attribute.
            if (linkElement.getAttribute('data-icon')) {
              linkElement.setAttribute('data-selected-icon', linkElement.getAttribute('data-icon'));
              linkElement.removeAttribute('data-icon');
            }

            // Remove data-selected-icon if user has removed the icon.
            if (
              !linkIsNew &&
              linkElement.getAttribute('data-selected-icon') &&
              !linkElement.getAttribute('data-cke-saved-data-selected-icon')
            ) {
              linkElement.removeAttribute('data-selected-icon');
            }
          }
          // Remove the possible spans and selected icon if they exist.
          else {
            handleLabelSpan(editor, linkElement, 'remove');
            if (linkElement.getAttribute('data-selected-icon')) {
              linkElement.removeAttribute('data-selected-icon');
            }
          }

          // Set link classes based on user selections.
          handleClasses(editor, linkElement, classList);
        }

        // Check if link text has changed and act accordingly.
        if (linkElement.$.dataset.linkText && linkElement.$.innerText) {
          if (linkElement.$.dataset.linkText !== linkElement.$.innerText) {
            linkElement.$.innerText = linkElement.$.dataset.linkText;
          }
        }
        editor.fire('saveSnapshot');
      }
    });
  }

  CKEDITOR.plugins.add('hds-button', {
    afterInit(editor) {
      alterDrupallinkPlugin(editor);
    },
  });
})(jQuery, Drupal, CKEDITOR);
