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

		this._show(Drupal.t('Check tone', {}, CONTEXT), this._comparisonView(), [
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

		// The dialog is now rendered in the DOM; fill the panes with sanitized
		// HTML so the content shows as rendered markup. Setting innerHTML (rather
		// than passing text through the template) is what renders it as HTML; the
		// sanitizer is what keeps that safe.
		const panes = editor.plugins.get('Dialog').view.element.querySelectorAll('.helfi-ai-tone-check__pane');
		if (panes[0]) {
			panes[0].innerHTML = this._previewHtml(original);
		}
		if (panes[1]) {
			panes[1].innerHTML = this._previewHtml(suggestion);
		}
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
	 * The panes are left empty here; _checkTone fills them with sanitized HTML
	 * after the dialog renders, so the content shows as rendered markup.
	 */
	_comparisonView() {
		const column = (heading) => ({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone-check__column'] },
			children: [
				{ tag: 'h3', attributes: { class: ['helfi-ai-tone-check__heading'] }, children: [heading] },
				{ tag: 'div', attributes: { class: ['helfi-ai-tone-check__pane'] } },
			],
		});
		const view = new View(this.editor.locale);
		view.setTemplate({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone-check', 'helfi-ai-tone-check__comparison'] },
			children: [column(Drupal.t('Original', {}, CONTEXT)), column(Drupal.t('Suggestion', {}, CONTEXT))],
		});
		return view;
	}

	/**
	 * Returns HTML to preview, sanitized by the editor's own conversion.
	 *
	 * The panes render via innerHTML, which bypasses CKEditor — so raw suggestion
	 * markup must not be injected as-is. Round-tripping through the editor's data
	 * pipeline (view → model → data) drops anything the text format does not
	 * allow (scripts, event handlers, unknown elements) using the *same* rules as
	 * the editor, and makes the preview match what Replace will produce. On any
	 * conversion error it falls back to escaped plain text (never raw HTML).
	 */
	_previewHtml(html) {
		const { data } = this.editor;
		try {
			return data.stringify(data.toModel(data.processor.toView(html)));
		} catch {
			const text = new DOMParser().parseFromString(html, 'text/html').body.textContent || '';
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
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
