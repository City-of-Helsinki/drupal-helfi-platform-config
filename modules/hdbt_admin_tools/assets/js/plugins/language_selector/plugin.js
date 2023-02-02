/**
 * Language selector plugin
 */

'use strict';

( function($) {

  const allowedContent = 'span[!lang,!dir]';
  const requiredContent = 'span[lang,dir]';

  CKEDITOR.plugins.add( 'language_selector', {
    lang: ['fi','en','sv'],
    icons: 'language_selector',
    hidpi: true,

    init: function( editor ) {
      editor.addCommand('handleLanguageSelector', new CKEDITOR.dialogCommand('languageSelectorDialog'));

      const plugin = this;
      const lang = editor.lang.language_selector;

      // Registers command.
      editor.addCommand( 'language', {
        allowedContent: allowedContent,
        requiredContent: requiredContent,
        contextSensitive: true,
        exec: function( editor, settings ) {
          const style = new CKEDITOR.style( { element: 'span', attributes: { 'lang': settings.languageId, 'dir': settings.direction } } );
          editor[(settings.remove) ? 'removeStyle' : 'applyStyle']( style );
        },
        refresh: function( editor ) {
          this.setState( plugin.getCurrentLangElement( editor ) ?
            CKEDITOR.TRISTATE_ON : CKEDITOR.TRISTATE_OFF );
        }
      } );

      editor.ui.addButton('language_selector', {
        label: lang.button,
        command: 'handleLanguageSelector',
        toolbar: 'insert,10',
        icon: this.path + "icons/language_selector.png"
      });

      if (editor.contextMenu) {

        editor.addMenuGroup('languageSelectorGroup');
        editor.addMenuItem('languageSelectorItem', {
          label: lang.button,
          icon: this.path + "icons/language_selector.png",
          command: 'handleLanguageSelector',
          group: 'languageSelectorGroup'
        });

        editor.contextMenu.addListener(function (element) {
          let parent = element.getAscendant('span');
          if (parent && parent.hasAttribute('lang')) {
            return { languageSelectorItem: CKEDITOR.TRISTATE_OFF };
          }
        });
      }
      CKEDITOR.dialog.add('languageSelectorDialog', this.path + 'dialogs/language_selector.js');

      // Prevent of removing `span` element with `lang` and `dir` attribute (#779).
      if ( editor.addRemoveFormatFilter ) {
        editor.addRemoveFormatFilter( function( element ) {
          return !( element.is( 'span' ) && element.getAttribute( 'dir' ) && element.getAttribute( 'lang' ) );
        } );
      }
    },

    // Gets the first language element for the current editor selection.
    getCurrentLangElement: function( editor ) {
      const elementPath = editor.elementPath();
      const activePath = elementPath && elementPath.elements;
      let pathMember;
      let ret;

      // Upon initialization if there is no path elementPath() returns null.
      if ( elementPath ) {
        for ( let i = 0; i < activePath.length; i++ ) {
          pathMember = activePath[ i ];

          if (
            !ret &&
            pathMember.getName() === 'span' &&
            pathMember.hasAttribute( 'dir' ) &&
            pathMember.hasAttribute( 'lang' )
          ) {
            ret = pathMember;
          }
        }
      }

      return ret;
    }
  } );
} )(jQuery);
