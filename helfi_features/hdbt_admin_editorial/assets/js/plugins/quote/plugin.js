/**
 * Text Quote plugin
 * Plugin for: http://ckeditor.com/license (GPL/LGPL/MPL: http://ckeditor.com/license)
 */

(function () {
  CKEDITOR.plugins.add('quote', {
    lang: ['fi','en'],
    icons: 'quote',
    hidpi: true,

    init: function (editor) {
      editor.addCommand('handleQuote', new CKEDITOR.dialogCommand('quoteDialog'));
      editor.ui.addButton('quote', {
        label: editor.lang.quote.quoteTitle,
        command: 'handleQuote',
        toolbar: 'insert,10',
        icon: this.path + "icons/quote.png"
      });

      if (editor.contextMenu) {
        editor.addMenuGroup('quoteGroup');
        editor.addMenuItem('quoteItem', {
          label: editor.lang.quote.handleQuote,
          icon: this.path + 'icons/quote.png',
          command: 'handleQuote',
          group: 'quoteGroup'
        });

        editor.contextMenu.addListener(function (element) {
          let parent = element.getAscendant('div');
          if (parent && parent.hasClass('quote')) {
            return { quoteItem: CKEDITOR.TRISTATE_OFF };
          }
        });
      }
      CKEDITOR.dialog.add('quoteDialog', this.path + 'dialogs/quote.js');
    }
  });
})();
