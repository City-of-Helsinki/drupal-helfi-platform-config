/**
 * @file Clean up the extra spaces and mark the intentional non-breaking spaces.
 */

import { Plugin } from 'ckeditor5/src/core';

const NBSP = '\u00A0';

/**
 * Convert non-breaking spaces to regular spaces and collapse repeated spaces.
 *
 * @param {module:engine/view/node~ViewNode} node
 *   The view node to process.
 * @param {Array} blockElements
 *   The names of the block elements.
 */
const normalizeSpaces = (node, blockElements) => {
  if (node.is('$text')) {
    node._data = node.data.replace(/\u00A0/g, ' ').replace(/ {2,}/g, ' ');
    return;
  }

  if (node.is('element', 'span') && node.hasAttribute('data-nbsp')) {
    const marked = node.getChild(0);

    if (marked?.is('$text')) {
      marked._data = NBSP;
    }
    return;
  }

  for (const child of Array.from(node.getChildren())) {
    normalizeSpaces(child, blockElements);
  }

  unwrapBlankSpans(node);
  joinTexts(node);

  if (node.is('documentFragment') || blockElements.includes(node.name)) {
    trimEdgeSpaces(node);
  }
};

/**
 * Unwrap the spans that has nothing but spaces.
 *
 * @param {module:engine/view/node~ViewNode} element
 *   The view element to process.
 */
const unwrapBlankSpans = (element) => {
  for (const child of Array.from(element.getChildren())) {
    if (!child.is('element', 'span') || child.hasAttribute('data-nbsp') || child.childCount !== 1) {
      continue;
    }

    const text = child.getChild(0);

    if (!text.is('$text') || text.data.trim() !== '') {
      continue;
    }

    const { index } = child;

    child._removeChildren(0, 1);
    element._removeChildren(index, 1);
    element._insertChild(index, [text]);
  }
};

/**
 * Join the neighboring text nodes and collapse the spaces between them.
 *
 * @param {module:engine/view/node~ViewNode} element
 *   The view element to process.
 */
const joinTexts = (element) => {
  let previous = null;

  for (const child of Array.from(element.getChildren())) {
    if (!child.is('$text')) {
      previous = null;
      continue;
    }

    if (previous) {
      previous._data = `${previous.data}${child.data}`.replace(/ {2,}/g, ' ');
      child._remove();
      continue;
    }

    previous = child;
  }
};

/**
 * Remove the spaces from the start and the end of a block.
 *
 * @param {module:engine/view/node~ViewNode} element
 *   The view element to process.
 */
const trimEdgeSpaces = (element) => {
  const first = element.getChild(0);

  if (first?.is('$text')) {
    first._data = first.data.replace(/^ /, '');
  }

  const last = element.getChild(element.childCount - 1);

  if (last?.is('$text')) {
    last._data = last.data.replace(/ $/, '');
  }

  for (const child of Array.from(element.getChildren())) {
    if (child.is('$text') && child.data === '') {
      child._remove();
    }
  }
};

/**
 * Remove the empty paragraphs from the start and the end of the content.
 *
 * @param {module:engine/view/documentfragment~ViewDocumentFragment} fragment
 *   The view fragment to process.
 */
const trimEmptyParagraphs = (fragment) => {
  const isEmptyParagraph = (node) => node.is('element', 'p') && node.isEmpty;

  while (fragment.childCount > 0 && isEmptyParagraph(fragment.getChild(0))) {
    fragment.getChild(0)._remove();
  }

  while (fragment.childCount > 0 && isEmptyParagraph(fragment.getChild(fragment.childCount - 1))) {
    fragment.getChild(fragment.childCount - 1)._remove();
  }
};

export default class HelfiNbspEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'HelfiNbspEditing';
  }

  init() {
    const { editor } = this;

    editor.model.schema.extend('$text', { allowAttributes: 'helfiNbsp' });
    editor.model.schema.setAttributeProperties('helfiNbsp', {
      isFormatting: false,
      copyOnEnter: false,
    });

    editor.conversion.attributeToElement({
      model: 'helfiNbsp',
      view: { name: 'span', attributes: { 'data-nbsp': '' } },
    });

    this._cleanUpContent();
  }

  afterInit() {
    this._markInsertedNbsp();
  }

  /**
   * Clean up the content on load and on paste.
   */
  _cleanUpContent() {
    const { processor } = this.editor.data;
    const { blockElements } = processor.domConverter;
    const toView = processor.toView.bind(processor);

    processor.toView = (data) => {
      const fragment = toView(data);
      normalizeSpaces(fragment, blockElements);
      trimEmptyParagraphs(fragment);

      return fragment;
    };
  }

  /**
   * Mark the non-breaking spaces the editor inserts on purpose.
   */
  _markInsertedNbsp() {
    const { model } = this.editor;

    this.listenTo(
      this.editor.commands.get('insertText'),
      'execute',
      (event, args) => {
        const [options = {}] = args;

        if (options.text !== NBSP) {
          return;
        }
        event.stop();

        model.change((writer) => {
          let selection = model.document.selection;

          if (options.selection) {
            selection = options.selection;
          } else if (options.range) {
            selection = model.createSelection(options.range);
          }

          model.insertContent(writer.createText(NBSP, { helfiNbsp: true }), selection);
          writer.removeSelectionAttribute('helfiNbsp');
        });
      },
      { priority: 'high' },
    );
  }
}
