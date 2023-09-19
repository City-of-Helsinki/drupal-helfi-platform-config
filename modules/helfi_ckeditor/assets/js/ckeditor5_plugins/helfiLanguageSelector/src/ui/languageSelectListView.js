import { View } from 'ckeditor5/src/ui';
import { getCode } from 'ckeditor5/src/utils';

/**
 * The LanguageSelectListView class.
 */
export default class LanguageSelectListView extends View {

  /**
   * @inheritDoc
   */
  constructor(locale, editor) {
    super(locale, editor);
    const bind = this.bindTemplate;
    const { t } = locale;

    this.editor = editor;

    this.set('isOpen', false);
    this.set('label');
    this.set('id', null);

    this.setTemplate({
      tag: 'select',
      attributes: {
        id: bind.to('id'),
        class: [
          'ck-helfi-select-list',
          bind.if('isOpen', 'ck-is-open', isOpen => isOpen)
        ],
        open: bind.if('isOpen'),
        placeholder: t('Select language'),
      },
      on: {
        keydown: bind.to(evt => {
          // Need to check target. Otherwise, we would handle space press on
          // input[type=text] and it would change checked property
          // twice due to default browser handling kicking in too.
          if (evt.target === this.element && evt.keyCode === getCode('space')) {
            this.isOpen = !this.isOpen;
          }
        }),
      },
    });
  }

  /**
   * Focuses the {@link #element} of the details.
   */
  focus() {
    this.element.focus();
  }

}
