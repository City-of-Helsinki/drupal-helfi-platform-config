/**
 * @file
 * The language selector dialog definition.
 */

( function($) {
  CKEDITOR.dialog.add('languageSelectorDialog', function (editor) {
    const lang = editor.lang.language_selector;
    let settings = {
      'languageId' : false,
      'direction' : true,
      'remove' : false,
    };

    // Gets the first language element for the current editor selection.
    function isCurrentElementSpan() {
      let element = editor.getSelection().getStartElement();
      return (element && element.is('span') && element.getAttribute('lang'))
        ? element
        : false;
    }

    return {
      title: lang.list_title,
      minWidth: 300,
      minHeight: 100,
      buttons: [ CKEDITOR.dialog.cancelButton, CKEDITOR.dialog.okButton ],

      // Invoked when the dialog is loaded.
      onLoad: function () {
        const languagesConfigStrings = ( editor.config.language_list || [ 'fi:Finnish', 'sv:Swedish', 'en:English' ] );

        // Empty option required for select2 placeholder text.
        let output = [
          '<select class="js-language-selector" name="language-selector">' +
          '<option></option>'
        ];

        // Construct the select list.
        for ( let i = 0; i < languagesConfigStrings.length; i++ ) {
          const parts = languagesConfigStrings[ i ].split( ':' );
          const langCode = parts[0];
          const direction = (!parts[2]) ? 'ltr' : 'rtl';
          const languageButtonId = 'language_' + langCode;
          const language = (lang[langCode]) ?? lang[langCode];
          if (language) {
            output.push(`<option id="${languageButtonId}" value="${langCode}:${direction}">${language}</option>` );
          }
        }

        output.push('</select>');

        // Set the HTML to selectContainer in languageSelect dialog.
        this.getContentElement( 'languageSelect', 'selectContainer' )
          .getElement()
          .setHtml( output.join( '' ) );
      },

      // Invoked when the dialog is opened.
      contents: [
        {
          id: 'languageSelect',
          label: editor.lang.common.generalTab,
          title: editor.lang.common.generalTab,
          align: 'top',
          elements: [
            {
              type: 'html',
              id: 'selectContainer',
              html: '',
              onShow: function(event) {
                const select = $(event.sender.parts.dialog.$).find('select.js-language-selector');

                // Remove selected options from possible previous sessions.
                select.val('').trigger('change');

                // Add current language as selected option in select list.
                if (isCurrentElementSpan()) {
                  select
                    .children(`option[id="language_${isCurrentElementSpan().getAttribute('lang')}"]`)
                    .attr('selected', 'selected')
                    .trigger('change');
                }

                // Init select2.
                if (!select.hasClass('select2-hidden-accessible')) {
                  select.select2({
                    width: '300px',
                    placeholder: lang.list_title,
                    allowClear: true
                  });
                }
              },
            },
            {
              type: 'button',
              id: 'removeSpan',
              label: lang.remove,
              className: 'language-selector__remove',
              title: lang.remove_description,
              onClick: function() {
                const langAttribute = editor.getSelection().getStartElement().getAttribute('lang');
                const dirAttribute = editor.getSelection().getStartElement().getAttribute('dir');

                // Remove language and simulate ok click.
                if ( langAttribute && dirAttribute) {
                  settings.remove = true;
                  settings.languageId = langAttribute;
                  settings.direction = dirAttribute;
                  this.getDialog().getButton('ok').click();
                }
              },
              onShow: function() {
                // Get "remove language" button.
                const button = $('#'+this.domId);

                // Hide remove button if nothing is selected.
                if (isCurrentElementSpan()) {
                  button.show();
                } else {
                  button.hide();
                }
              },
            }
          ]
        }
      ],

      // Dialog contents
      onShow: function () {
        // Select list.
        const selectList = $(
          this.getContentElement('languageSelect', 'selectContainer').getElement().$
        ).children('select');

        // Add onChange listener for the select list and update the settings
        // based on selections.
        selectList.change(function(event) {
          const parts = $(event.target).val().split(':');
          settings.remove = false;
          settings.languageId = parts[0];
          settings.direction = parts[1];
        });
      },

      // When ok button is clicked.
      onOk: function () {
        if (settings.languageId) {
          editor.execCommand( 'language', settings);
        }
      }
    };
  });
})(jQuery);
