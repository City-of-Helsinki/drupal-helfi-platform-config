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

/**
 * Check if the model node is a blank text or a br.
 *
 * @param {module:engine/model/node~ModelNode} node
 *   The model node to check.
 *
 * @return {boolean}
 *   Returns true if the node is blank.
 */
const isBlank = (node) => {
  if (node.is('$text')) {
    return node.data.trim() === '';
  }
  return node.is('element', 'softBreak');
};

/**
 * Find the first run of two or more brs in a paragraph.
 *
 * @param {module:engine/model/element~ModelElement} paragraph
 *   The model paragraph to search.
 *
 * @return {Array|null}
 *   Returns the first and the last node of the run or null.
 */
const findBreakRun = (paragraph) => {
  const children = Array.from(paragraph.getChildren());

  for (let start = 0; start < children.length; start++) {
    // Start the run from a br.
    if (!children[start].is('element', 'softBreak')) {
      continue;
    }

    let end = start;
    let breaks = 0;

    // Collect the brs and blank texts that follow.
    while (end < children.length && isBlank(children[end])) {
      if (children[end].is('element', 'softBreak')) {
        breaks++;
      }
      end++;
    }

    if (breaks > 1) {
      return [children[start], children[end - 1]];
    }

    // Continue the search after a single br.
    start = end;
  }

  return null;
};

/**
 * Remove the blank nodes and spaces from the start and the end of a paragraph.
 *
 * @param {module:engine/model/writer~ModelWriter} writer
 *   The model writer.
 * @param {module:engine/model/element~ModelElement} paragraph
 *   The model paragraph to trim.
 */
const trimParagraph = (writer, paragraph) => {
  // Remove the leading brs and blank texts.
  while (paragraph.childCount > 0 && isBlank(paragraph.getChild(0))) {
    writer.remove(paragraph.getChild(0));
  }

  // Remove the trailing brs and blank texts.
  while (paragraph.childCount > 0 && isBlank(paragraph.getChild(paragraph.childCount - 1))) {
    writer.remove(paragraph.getChild(paragraph.childCount - 1));
  }

  // Remove the leading spaces from the first text.
  const first = paragraph.getChild(0);

  if (first?.is('$text')) {
    // Count the leading spaces.
    const length = first.data.length - first.data.trimStart().length;

    // Remove the spaces from the start of the paragraph.
    if (length > 0) {
      writer.remove(
        writer.createRange(writer.createPositionAt(paragraph, 0), writer.createPositionAt(paragraph, length)),
      );
    }
  }

  // Remove the trailing spaces from the last text.
  const last = paragraph.getChild(paragraph.childCount - 1);

  if (last?.is('$text')) {
    // Count the trailing spaces.
    const length = last.data.length - last.data.trimEnd().length;

    // Remove the spaces from the end of the paragraph.
    if (length > 0) {
      writer.remove(
        writer.createRange(
          writer.createPositionAt(paragraph, paragraph.maxOffset - length),
          writer.createPositionAt(paragraph, paragraph.maxOffset),
        ),
      );
    }
  }
};

/**
 * Split the paragraphs at double brs and remove the empty paragraphs.
 *
 * @param {module:engine/model/writer~ModelWriter} writer
 *   The model writer.
 * @param {module:engine/model/range~ModelRange} range
 *   The model range to process.
 */
const cleanUpParagraphs = (writer, range) => {
  const paragraphs = Array.from(range.getItems()).filter((item) => item.is('element', 'paragraph'));
  const cleaned = [];

  for (let paragraph of paragraphs) {
    let run = findBreakRun(paragraph);

    while (run) {
      const [start, end] = run;
      const position = writer.createPositionBefore(start);

      // Remove the brs and split the paragraph in their place.
      writer.remove(writer.createRange(position, writer.createPositionAfter(end)));
      const { position: splitPosition } = writer.split(position);

      trimParagraph(writer, paragraph);
      cleaned.push(paragraph);

      // Continue with the new paragraph after the split.
      paragraph = splitPosition.nodeAfter;
      run = findBreakRun(paragraph);
    }

    trimParagraph(writer, paragraph);
    cleaned.push(paragraph);
  }

  // Remove the empty paragraphs that are not the only child of their parent.
  for (const paragraph of cleaned) {
    if (paragraph.isEmpty && paragraph.parent.childCount > 1) {
      writer.remove(paragraph);
    }
  }
};

/**
 * Expand the range to cover the paragraphs at its edges.
 *
 * @param {module:engine/model/writer~ModelWriter} writer
 *   The model writer.
 * @param {module:engine/model/range~ModelRange} range
 *   The model range to expand.
 *
 * @return {module:engine/model/range~ModelRange}
 *   Returns the expanded range.
 */
const expandToParagraphs = (writer, range) => {
  const first = range.start.findAncestor('paragraph');
  const last = range.end.findAncestor('paragraph');

  return writer.createRange(
    first ? writer.createPositionBefore(first) : range.start,
    last ? writer.createPositionAfter(last) : range.end,
  );
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
    this._cleanUpParagraphs();
    this._splitOnDoubleSoftBreak();
  }

  /**
   * Split the paragraph when a br is added after another br.
   */
  _splitOnDoubleSoftBreak() {
    const { editor } = this;
    const { model } = editor;
    const shiftEnter = editor.commands.get('shiftEnter');

    if (!shiftEnter) {
      return;
    }

    this.listenTo(
      shiftEnter,
      'execute',
      (event) => {
        const { selection } = model.document;

        // Skip when text is selected.
        if (!selection.isCollapsed) {
          return;
        }

        const position = selection.getFirstPosition();

        // Only split paragraphs.
        if (!position.parent.is('element', 'paragraph')) {
          return;
        }

        // Find the node before the cursor, skip blank texts.
        let previous = position.nodeBefore;

        while (previous?.is('$text') && previous.data.trim() === '') {
          previous = previous.previousSibling;
        }

        // Add a br when there is no br before the cursor.
        if (!previous?.is('element', 'softBreak')) {
          return;
        }

        // Replace the br with a new paragraph.
        event.stop();

        model.change((writer) => {
          // Remove the previous br and the spaces after it.
          writer.remove(writer.createRange(writer.createPositionBefore(previous), position));
          editor.execute('enter');

          // Trim the new paragraph and the paragraph before it.
          const paragraph = model.document.selection.getFirstPosition().parent;
          trimParagraph(writer, paragraph);

          if (paragraph.previousSibling?.is('element', 'paragraph')) {
            trimParagraph(writer, paragraph.previousSibling);
          }
        });
      },
      { priority: 'high' },
    );
  }

  /**
   * Clean up the paragraphs on load, on paste and when the editor loses focus.
   */
  _cleanUpParagraphs() {
    const { editor } = this;
    const { model } = editor;

    const cleanUpOnLoad = () => {
      model.enqueueChange({ isUndoable: false }, (writer) => {
        cleanUpParagraphs(writer, writer.createRangeIn(model.document.getRoot()));
      });
    };

    this.listenTo(editor.data, 'init', cleanUpOnLoad, { priority: 'low' });
    this.listenTo(editor.data, 'set', cleanUpOnLoad, { priority: 'low' });

    if (editor.plugins.has('ClipboardPipeline')) {
      this.listenTo(
        editor.plugins.get('ClipboardPipeline'),
        'contentInsertion',
        (_event, data) => {
          if (!data.resultRange) {
            return;
          }

          model.change((writer) => {
            cleanUpParagraphs(writer, expandToParagraphs(writer, data.resultRange));
          });
        },
        { priority: 'lowest' },
      );
    }

    this.listenTo(editor.ui.focusTracker, 'change:isFocused', (_event, _name, isFocused) => {
      if (isFocused || editor.isReadOnly) {
        return;
      }

      model.change((writer) => {
        cleanUpParagraphs(writer, writer.createRangeIn(model.document.getRoot()));
      });
    });
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
