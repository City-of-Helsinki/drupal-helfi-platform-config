/**
 * @file Adds the "Check tone" toolbar button.
 *
 * Clicking it sends the full editor content to the helfi_ai tone-check endpoint
 * and opens a dialog showing the original and the suggested rewrite side by
 * side. Confirming replaces the whole editor content with the suggestion.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView, Dialog, View } from 'ckeditor5/src/ui';
import { diffArrays } from 'diff';
import icon from '../../../../icons/helfiAiToneCheck.svg';

// Split HTML into atomic tokens: whole tags, whitespace runs, and words.
const tokenizeHtml = (html) => {
	return html.match(/<[^>]+>|\s+|[^<\s]+/g) ?? [];
};

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
				label: Drupal.t('Check tone', {}, { context: 'Helfi AI' }),
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
			Drupal.t('Checking tone…', {}, { context: 'Helfi AI' }),
			this._messageView(Drupal.t('Checking the tone of the content…', {}, { context: 'Helfi AI' })),
			[],
			'loading',
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
				Drupal.t('Check tone', {}, { context: 'Helfi AI' }),
				this._messageView(Drupal.t('Could not check the tone. Please try again.', {}, { context: 'Helfi AI' })),
				[this._closeButton()],
				'error',
			);
			return;
		}

		this._show(Drupal.t('Check tone', {}, { context: 'Helfi AI' }), this._tabbedView(), [
			{
				label: Drupal.t('Replace the content with an AI-generated version', {}, { context: 'Helfi AI' }),
				withText: true,
				class: 'ck-reset_all-excluded helfi-ai-tone__reset',
				onCreate: (button) => {
					const classes = button.labelView.template.attributes.class;
					const i = classes.indexOf('ck-button__label');
					if (i !== -1) {
						classes.splice(i, 1);
					}
					classes.push('button', 'button--small', 'button--primary');
				},
				onExecute: () => {
					editor.setData(suggestion);
					editor.plugins.get('Dialog').hide();
				},
			},
			{
				label: Drupal.t('Cancel'),
				withText: true,
				class: 'ck-reset_all-excluded helfi-ai-tone__reset',
				onCreate: (button) => {
					const classes = button.labelView.template.attributes.class;
					const i = classes.indexOf('ck-button__label');
					if (i !== -1) {
						classes.splice(i, 1);
					}
					classes.push('button', 'button--small', 'button--secondary');
				},
				onExecute: () => editor.plugins.get('Dialog').hide(),
			},
		]);

		// The dialog is now rendered in the DOM; fill the panes with sanitized
		// HTML so the content shows as rendered markup. Setting innerHTML (rather
		// than passing text through the template) is what renders it as HTML; the
		// sanitizer is what keeps that safe.
		const root = editor.plugins.get('Dialog').view.element;
		const originalHtml = this._previewHtml(original);
		const suggestionHtml = this._previewHtml(suggestion);
		const diffHtml = this._diffHtml(originalHtml, suggestionHtml);

		const fill = (name, html) => {
			const pane = root.querySelector(`.helfi-ai-tone__pane[data-pane="${name}"]`);
			if (pane) {
				pane.innerHTML = html;
			}
		};
		fill('comparison-original', diffHtml);
		fill('comparison-suggestion', diffHtml);
		fill('original', originalHtml);
		fill('suggestion', suggestionHtml);

		// Wire tab switching and open the default tab.
		root.querySelectorAll('.helfi-ai-tone__tab').forEach((tab) => {
			tab.addEventListener('click', () => this._activateTab(root, tab.dataset.tab));
		});
		this._activateTab(root, 'comparison');
	}

	/**
	 * Highlights insertions and deletions between the original and suggestion.
	 *
	 * Tags are emitted as-is; only text tokens are wrapped so the preview markup
	 * stays valid.
	 */
	_diffHtml(original, suggestion) {
		const parts = diffArrays(tokenizeHtml(original), tokenizeHtml(suggestion));
		const wrap = (tokens, tag, className) =>
			tokens
				.map((token) => (token.startsWith('<') ? token : `<${tag} class="${className}">${token}</${tag}>`))
				.join('');
		return parts
			.map((part) => {
				if (part.added) {
					return wrap(part.value, 'ins', 'helfi-ai-tone__ins');
				}
				if (part.removed) {
					return wrap(part.value, 'del', 'helfi-ai-tone__del');
				}
				return part.value.join('');
			})
			.join('');
	}

	/**
	 * Shows (or re-renders) the tone-check dialog.
	 */
	_show(title, content, actionButtons, variant = 'default') {
		const dialog = this.editor.plugins.get('Dialog');
		dialog.show({
			id: 'helfiAiToneCheck',
			title,
			content,
			actionButtons,
			// Mark the overlay and content so the theme can scope the dialog styles.
			// ck-reset_all-excluded opts the content subtree out of CKEditor's reset.
			onShow: () => {
				const { element } = dialog.view;
				element.classList.add('helfi-ai-tone__dialog', 'ck-reset_all-excluded');
				element.classList.toggle('helfi-ai-tone__dialog--loading', variant === 'loading');
				element.classList.toggle('helfi-ai-tone__dialog--error', variant === 'error');
				dialog.view.contentView.element.classList.add('helfi-ai-tone__wrapper');
			},
			onHide: () =>
				dialog.view.element.classList.remove(
					'helfi-ai-tone__dialog',
					'helfi-ai-tone__dialog--loading',
					'helfi-ai-tone__dialog--error',
				),
		});
	}

	/**
	 * Builds a single-message dialog body (loading or error states).
	 */
	_messageView(message) {
		const view = new View(this.editor.locale);
		view.setTemplate({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone', 'helfi-ai-tone--message'] },
			children: [message],
		});
		return view;
	}

	/**
	 * Builds the tabbed comparison dialog body.
	 *
	 * The panes are left empty here; _checkTone fills them with sanitized HTML
	 * after the dialog renders, so the content shows as rendered markup.
	 */
	_tabbedView() {
		// A tab button in the tablist.
		const tab = (id, label) => ({
			tag: 'button',
			attributes: { type: 'button', class: ['helfi-ai-tone__tab'], 'data-tab': id },
			children: [label],
		});
		// An empty pane to fill after render.
		const pane = (name) => ({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone__pane', 'ck-content'], 'data-pane': name },
		});
		// A headed column inside the comparison panel.
		const column = (heading, name) => ({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone__column'] },
			children: [{ tag: 'h3', attributes: { class: ['helfi-ai-tone__heading'] }, children: [heading] }, pane(name)],
		});
		// A tab panel wrapping its content.
		const panel = (id, children) => ({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone__panel'], 'data-panel': id },
			children,
		});

		const view = new View(this.editor.locale);
		view.setTemplate({
			tag: 'div',
			attributes: { class: ['helfi-ai-tone__content'] },
			children: [
				{
					tag: 'div',
					attributes: { class: ['helfi-ai-tone__tabs'] },
					children: [
						tab('comparison', Drupal.t('Comparison', {}, { context: 'Helfi AI' })),
						tab('original', Drupal.t('Original content', {}, { context: 'Helfi AI' })),
						tab('suggestion', Drupal.t('Suggested content', {}, { context: 'Helfi AI' })),
					],
				},
				{
					tag: 'div',
					attributes: { class: ['helfi-ai-tone__panels'] },
					children: [
						panel('comparison', [
							{
								tag: 'div',
								attributes: { class: ['helfi-ai-tone__comparison'] },
								children: [
									column(Drupal.t('Original content', {}, { context: 'Helfi AI' }), 'comparison-original'),
									column(Drupal.t('Suggested content', {}, { context: 'Helfi AI' }), 'comparison-suggestion'),
								],
							},
						]),
						panel('original', [pane('original')]),
						panel('suggestion', [pane('suggestion')]),
					],
				},
			],
		});
		return view;
	}

	/**
	 * Shows the panel for the given tab and marks its tab active.
	 */
	_activateTab(root, id) {
		root.querySelectorAll('.helfi-ai-tone__tab').forEach((tab) => {
			tab.classList.toggle('helfi-ai-tone__tab--active', tab.dataset.tab === id);
		});
		root.querySelectorAll('.helfi-ai-tone__panel').forEach((panel) => {
			panel.classList.toggle('helfi-ai-tone__panel--active', panel.dataset.panel === id);
		});
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
	 * A single "Cancel" action button that hides the dialog.
	 */
	_closeButton() {
		return {
			label: Drupal.t('Close', {}, { context: 'Helfi AI' }),
			withText: true,
			class: 'ck-reset_all-excluded helfi-ai-tone__reset',
			onExecute: () => this.editor.plugins.get('Dialog').hide(),
			onCreate: (button) => {
				const classes = button.labelView.template.attributes.class;
				const i = classes.indexOf('ck-button__label');
				if (i !== -1) {
					classes.splice(i, 1);
				}
				classes.push('button', 'button--small', 'button--secondary');
			},
		};
	}
}
