/**
 * @file
 * Streams a generated AI summary into the summary editor.
 */
((Drupal) => {
  /**
   * Reads server-sent events from a response and passes on their JSON data.
   */
  const readEvents = async (response, onData) => {
    const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
    let buffer = '';

    for (;;) {
      const { value, done } = await reader.read();
      if (done) {
        return;
      }
      buffer += value;

      let end = buffer.indexOf('\n\n');
      while (end !== -1) {
        const data = buffer
          .slice(0, end)
          .split('\n')
          .find((line) => line.startsWith('data:'));
        buffer = buffer.slice(end + 2);
        if (data) {
          onData(JSON.parse(data.slice(5)));
        }
        end = buffer.indexOf('\n\n');
      }
    }
  };

  /**
   * Streams the summary into the editor, showing items as they complete.
   *
   * Core AJAX waits for the returned promise, so the button can't be
   * clicked again while the summary is streaming.
   */
  Drupal.AjaxCommands.prototype.helfiAiSummaryStream = async (ajax, response) => {
    const wrapper = document.getElementById(response.wrapperId);
    const button = wrapper.querySelector('[name^="ai_summary_generate_"]');
    const textarea = wrapper.querySelector('textarea[data-ckeditor5-id]');
    const editor = Drupal.CKEditor5Instances.get(textarea.dataset.ckeditor5Id);
    const hiddenBox = textarea.closest('.hidden');
    const previous = editor.getData();

    wrapper.querySelector('.messages--error')?.remove();
    button.insertAdjacentHTML(
      'afterend',
      Drupal.theme('ajaxProgressThrobber', Drupal.t('Generating summary…', {}, { context: 'Helfi AI' })),
    );
    const throbber = button.nextElementSibling;
    button.disabled = true;
    hiddenBox?.classList.remove('hidden');
    editor.enableReadOnlyMode('helfi-ai-summary');

    try {
      const stream = await fetch(response.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': response.csrfToken },
        body: JSON.stringify({ text: response.text, langcode: response.langcode }),
      });
      if (!stream.ok) {
        throw new Error(`Summary request failed: ${stream.status}`);
      }

      let result = null;
      await readEvents(stream, (data) => {
        if (data.error) {
          throw new Error('Summary failed.');
        }
        if (data.items) {
          editor.setData(`<ul>${data.items.map((item) => `<li>${Drupal.checkPlain(item)}</li>`).join('')}</ul>`);
        }
        if (data.done) {
          result = data.result;
        }
      });
      if (result === null) {
        throw new Error('Summary stream ended early.');
      }

      editor.setData(result);
      button.value = Drupal.t('Regenerate AI summary', {}, { context: 'Helfi AI' });
      button.setAttribute(
        'data-ai-summary-confirm',
        Drupal.t('Regenerating replaces the current AI summary, including any manual changes. Continue?', {}, { context: 'Helfi AI' }),
      );
      wrapper.querySelector(':scope > .description').textContent = Drupal.t(
        'Generate a new AI summary. It will replace the previous summary.',
        {},
        { context: 'Helfi AI' },
      );
      Drupal.behaviors.helfiAiSummaryConfirm.attach(wrapper);
    } catch {
      editor.setData(previous);
      hiddenBox?.classList.add('hidden');
      wrapper.insertAdjacentHTML(
        'afterbegin',
        `<p class="messages messages--error">${Drupal.t('Could not generate a summary. Add some page content and make sure the AI provider is configured.', {}, { context: 'Helfi AI' })}</p>`,
      );
    } finally {
      throbber.remove();
      button.disabled = false;
      editor.disableReadOnlyMode('helfi-ai-summary');
    }
  };
})(Drupal);
