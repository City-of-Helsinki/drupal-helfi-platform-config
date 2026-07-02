/**
 * @file Adds the "Check tone" toolbar button.
 *
 * Clicking it sends the full editor content to the helfi_ai tone-check endpoint
 * and opens a dialog showing the original and the suggested rewrite side by
 * side. Confirming replaces the whole editor content with the suggestion.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView, Dialog, View } from 'ckeditor5/src/ui';
import icon from '../../../../icons/helfiAiToneCheck.svg';

// Translation context shared by all user-facing strings in this plugin.
const CONTEXT = { context: 'CKEditor5 Helfi AI tone check plugin' };

export default class HelfiAiToneCheckUi extends Plugin {
	static get requires() {
		return [Dialog];
	}

	static get pluginName() {
		return 'HelfiAiToneCheckUi';
	}

	init() {
		const { editor } = this;

		editor.ui.componentFactory.add('helfiAiToneCheck', (locale) => {
			const button = new ButtonView(locale);
			button.set({
				label: Drupal.t('Check tone', {}, CONTEXT),
				icon,
				tooltip: true,
			});
			this.listenTo(button, 'execute', () => this._checkTone());
			return button;
		});
	}

	/**
	 * Requests a tone-conforming rewrite and shows the comparison dialog.
	 */
	async _checkTone() {
		const { editor } = this;
		const config = editor.config.get('helfiAiToneCheck') || {};
		const original = editor.getData();

		if (!original.trim()) {
			return;
		}

		this._show(
			Drupal.t('Checking tone…', {}, CONTEXT),
			this._messageView(Drupal.t('Checking the tone of the content…', {}, CONTEXT)),
			[],
		);

		let suggestion;
		try {
			const response = await fetch(config.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-Token': config.csrfToken,
				},
				body: JSON.stringify({ content: original, langcode: config.langcode }),
			});
			if (!response.ok) {
				throw new Error(`Tone check request failed: ${response.status}`);
			}
			const data = await response.json();
			if (typeof data?.suggestion !== 'string' || data.suggestion === '') {
				throw new Error('Tone check returned no suggestion.');
			}
			suggestion = data.suggestion;
		} catch {
			this._show(
				Drupal.t('Check tone', {}, CONTEXT),
				this._messageView(Drupal.t('Could not check the tone. Please try again.', {}, CONTEXT)),
				[this._closeButton()],
			);
			return;
		}

		this._show(Drupal.t('Check tone', {}, CONTEXT), this._comparisonView(original, suggestion), [
			{
				label: Drupal.t('Cancel', {}, CONTEXT),
				withText: true,
				onExecute: () => editor.plugins.get('Dialog').hide(),
			},
			{
				label: Drupal.t('Replace', {}, CONTEXT),
				withText: true,
				class: 'ck-button-action',
				onExecute: () => {
					editor.setData(suggestion);
					editor.plugins.get('Dialog').hide();
				},
			},
		]);
	}

	/**
	 * Shows (or re-renders) the tone-check dialog.
	 */
	_show(title, content, actionButtons) {
		this.editor.plugins.get('Dialog').show({
			id: 'helfiAiToneCheck',
			title,
			content,
			actionButtons,
		});
	}

	/**
	 * Builds a single-message dialog body (loading or error states).
	 */
	_messageView(message) {
		const view = new View(this.editor.locale);
		view.setTemplate({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone-check', 'helfi-ai-tone-check__message'] },
			children: [message],
		});
		return view;
	}

	/**
	 * Builds the original-vs-suggestion comparison dialog body.
	 *
	 * Both sides are shown as readable plain text (markup stripped) so editors
	 * compare wording, not tags. A richer diff can be layered on later.
	 */
	_comparisonView(original, suggestion) {
		const column = (heading, body) => ({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone-check__column'] },
			children: [
				{ tag: 'h3', attributes: { class: ['helfi-ai-tone-check__heading'] }, children: [heading] },
				{ tag: 'div', attributes: { class: ['helfi-ai-tone-check__pane'] }, children: [body] },
			],
		});
		const view = new View(this.editor.locale);
		view.setTemplate({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone-check', 'helfi-ai-tone-check__comparison'] },
			children: [
				column(Drupal.t('Original', {}, CONTEXT), this._htmlToText(original)),
				column(Drupal.t('Suggestion', {}, CONTEXT), this._htmlToText(suggestion)),
			],
		});
		return view;
	}

	/**
	 * Converts an HTML string to readable plain text.
	 *
	 * Parsing with DOMParser does not execute scripts, and only the resulting
	 * textContent is used, so untrusted markup in the suggestion cannot run.
	 * Block-level elements become line breaks so multi-paragraph content stays
	 * readable (the pane renders with white-space: pre-wrap).
	 */
	_htmlToText(html) {
		const doc = new DOMParser().parseFromString(html, 'text/html');
		doc.body.querySelectorAll('p, br, li, h1, h2, h3, h4, h5, h6, div, tr').forEach((element) => {
			element.insertAdjacentText('afterend', '\n');
		});
		return (doc.body.textContent || '').replace(/\n{3,}/g, '\n\n').trim();
	}

	/**
	 * A single "Close" action button that hides the dialog.
	 */
	_closeButton() {
		return {
			label: Drupal.t('Close', {}, CONTEXT),
			withText: true,
			onExecute: () => this.editor.plugins.get('Dialog').hide(),
		};
	}
}
