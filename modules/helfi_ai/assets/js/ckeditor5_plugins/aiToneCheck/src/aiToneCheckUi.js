/**
 * @file Add the Check tone toolbar button and comparison dialog.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView, Dialog, View } from 'ckeditor5/src/ui';
import { diffArrays } from 'diff';
import icon from '../../../../icons/aiToneCheck.svg';

// Split HTML into tag, whitespace, and word tokens.
const tokenizeHtml = (html) => {
	return html.match(/<[^>]+>|\s+|[^<\s]+/g) ?? [];
};

export default class aiToneCheckUi extends Plugin {
	static get requires() {
		return [Dialog];
	}

	static get pluginName() {
		return 'aiToneCheckUi';
	}

	init() {
		const { editor } = this;

		editor.ui.componentFactory.add('aiToneCheck', (locale) => {
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
	 * Check the tone and show the dialog.
	 *
	 * The rewrite is streamed into the Suggested tab as it arrives. Once it is
	 * complete, the comparison and the Replace action are shown.
	 */
	async _checkTone() {
		const { editor } = this;
		const config = editor.config.get('aiToneCheck') || {};
		const original = editor.getData();

		if (!original.trim()) {
			return;
		}

		// A new check replaces any check still running.
		this._controller?.abort();
		const controller = new AbortController();
		this._controller = controller;

		this._show(
			Drupal.t('Checking tone…', {}, { context: 'Helfi AI' }),
			this._messageView(Drupal.t('Checking the tone of the content…', {}, { context: 'Helfi AI' })),
			[],
			'loading',
		);

		let suggestion = null;
		try {
			const response = await fetch(config.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-Token': config.csrfToken,
				},
				body: JSON.stringify({ content: original, langcode: config.langcode }),
				signal: controller.signal,
			});
			if (!response.ok) {
				throw new Error(`Tone check request failed: ${response.status}`);
			}

			let text = '';
			await Drupal.helfiAi.readEvents(response, (data) => {
				if (data.error) {
					throw new Error('Tone check failed.');
				}
				if (this._isStale(controller)) {
					throw new Error('Tone check is no longer shown.');
				}
				if (data.done) {
					suggestion = data.result;
					return;
				}
				if (!text) {
					this._show(Drupal.t('Check tone', {}, { context: 'Helfi AI' }), this._tabbedView(), [this._cancelButton()]);
					this._initTabs('suggestion');
					this._fillPane('original', this._previewHtml(original));
				}
				text += data.delta;
				this._fillPane('suggestion', this._previewHtml(text));
			});
			if (suggestion === null) {
				throw new Error('Tone check stream ended early.');
			}
		} catch {
			if (!this._isStale(controller)) {
				this._show(
					Drupal.t('Check tone', {}, { context: 'Helfi AI' }),
					this._messageView(
						Drupal.t('Could not complete the AI request. Please try again.', {}, { context: 'Helfi AI' }),
					),
					[this._closeButton()],
					'error',
				);
			}
			return;
		}

		if (this._isStale(controller)) {
			return;
		}

		this._show(Drupal.t('Check tone', {}, { context: 'Helfi AI' }), this._tabbedView(), [
			{
				label: Drupal.t('Replace the content with an AI-generated version', {}, { context: 'Helfi AI' }),
				withText: true,
				class: 'ck-reset_all-excluded ai-tone__reset',
				onCreate: this._styleActionButton('primary'),
				onExecute: () => {
					editor.setData(suggestion);
					editor.plugins.get('Dialog').hide();
				},
			},
			this._cancelButton(),
		]);

		const originalHtml = this._previewHtml(original);
		const suggestionHtml = this._previewHtml(suggestion);
		const diffHtml = this._diffHtml(originalHtml, suggestionHtml);

		this._fillPane('comparison-original', diffHtml);
		this._fillPane('comparison-suggestion', diffHtml);
		this._fillPane('original', originalHtml);
		this._fillPane('suggestion', suggestionHtml);
		this._initTabs('comparison');
	}

	/**
	 * Whether a check was replaced by a newer one or its dialog was closed.
	 */
	_isStale(controller) {
		return controller.signal.aborted || this.editor.plugins.get('Dialog').id !== 'aiToneCheck';
	}

	/**
	 * Fill a pane of the tabbed view with sanitized HTML.
	 */
	_fillPane(name, html) {
		this.editor.plugins.get('Dialog').view.element.querySelector(`.ai-tone__pane[data-pane="${name}"]`).innerHTML =
			html;
	}

	/**
	 * Make the tabs of the tabbed view clickable and show the given tab.
	 */
	_initTabs(id) {
		const root = this.editor.plugins.get('Dialog').view.element;
		root.querySelectorAll('.ai-tone__tab').forEach((tab) => {
			tab.addEventListener('click', () => this._activateTab(root, tab.dataset.tab));
		});
		this._activateTab(root, id);
	}

	/**
	 * Show or re-render the tone-check dialog.
	 */
	_show(title, content, actionButtons, variant = 'default') {
		const dialog = this.editor.plugins.get('Dialog');
		dialog.show({
			id: 'aiToneCheck',
			title,
			content,
			actionButtons,
			onShow: () => {
				const { element } = dialog.view;
				element.classList.add('ai-tone__dialog', 'ck-reset_all-excluded');
				element.classList.toggle('ai-tone__dialog--loading', variant === 'loading');
				element.classList.toggle('ai-tone__dialog--error', variant === 'error');
				dialog.view.contentView.element.classList.add('ai-tone__wrapper');
			},
			onHide: () =>
				dialog.view.element.classList.remove('ai-tone__dialog', 'ai-tone__dialog--loading', 'ai-tone__dialog--error'),
		});
	}

	/**
	 * Build the loading or error dialog body.
	 */
	_messageView(message) {
		const view = new View(this.editor.locale);
		view.setTemplate({
			tag: 'div',
			attributes: { class: ['ai-tone', 'ai-tone--message'] },
			children: [message],
		});
		return view;
	}

	/**
	 * Build the tabbed comparison dialog body.
	 */
	_tabbedView() {
		const tab = (id, label) => ({
			tag: 'button',
			attributes: { type: 'button', class: ['ai-tone__tab'], 'data-tab': id },
			children: [label],
		});
		const pane = (name) => ({
			tag: 'div',
			attributes: { class: ['ai-tone__pane', 'ck-content'], 'data-pane': name },
		});
		const column = (heading, name) => ({
			tag: 'div',
			attributes: { class: ['ai-tone__column'] },
			children: [{ tag: 'h3', attributes: { class: ['ai-tone__heading'] }, children: [heading] }, pane(name)],
		});
		const panel = (id, children) => ({
			tag: 'div',
			attributes: { class: ['ai-tone__panel'], 'data-panel': id },
			children,
		});

		const view = new View(this.editor.locale);
		view.setTemplate({
			tag: 'div',
			attributes: { class: ['ai-tone__content'] },
			children: [
				{
					tag: 'div',
					attributes: { class: ['ai-tone__tabs'] },
					children: [
						tab('comparison', Drupal.t('Comparison', {}, { context: 'Helfi AI' })),
						tab('original', Drupal.t('Original content', {}, { context: 'Helfi AI' })),
						tab('suggestion', Drupal.t('Suggested content', {}, { context: 'Helfi AI' })),
					],
				},
				{
					tag: 'div',
					attributes: { class: ['ai-tone__panels'] },
					children: [
						panel('comparison', [
							{
								tag: 'div',
								attributes: { class: ['ai-tone__comparison'] },
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
	 * Show the panel for the given tab and mark its tab active.
	 */
	_activateTab(root, id) {
		root.querySelectorAll('.ai-tone__tab').forEach((tab) => {
			tab.classList.toggle('ai-tone__tab--active', tab.dataset.tab === id);
		});
		root.querySelectorAll('.ai-tone__panel').forEach((panel) => {
			panel.classList.toggle('ai-tone__panel--active', panel.dataset.panel === id);
		});
	}

	/**
	 * Highlight insertions and deletions between original and suggested content.
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
					return wrap(part.value, 'ins', 'ai-tone__ins');
				}
				if (part.removed) {
					return wrap(part.value, 'del', 'ai-tone__del');
				}
				return part.value.join('');
			})
			.join('');
	}

	/**
	 * Return the HTML through the editor's own conversion.
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
	 * Swap CKEditor's default label class for Gin button classes.
	 */
	_styleActionButton(modifier) {
		return (button) => {
			const classes = button.labelView.template.attributes.class;
			const i = classes.indexOf('ck-button__label');
			if (i !== -1) {
				classes.splice(i, 1);
			}
			classes.push('button', 'button--small', `button--${modifier}`);
		};
	}

	/**
	 * Build the Close action button that hides the dialog.
	 */
	_closeButton() {
		return {
			label: Drupal.t('Close', {}, { context: 'Helfi AI' }),
			withText: true,
			class: 'ck-reset_all-excluded ai-tone__reset',
			onCreate: this._styleActionButton('secondary'),
			onExecute: () => this.editor.plugins.get('Dialog').hide(),
		};
	}

	/**
	 * Build the Cancel action button that hides the dialog.
	 */
	_cancelButton() {
		return { ...this._closeButton(), label: Drupal.t('Cancel') };
	}
}
