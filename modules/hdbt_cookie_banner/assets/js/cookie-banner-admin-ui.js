(function (Drupal, drupalSettings) {
  Drupal.behaviors.cookieBannerAdminUi = {
    attach: function attach() {
      const element = document.getElementById('editor_holder');
      const textarea = document.getElementById('edit-site-settings');
      let isUpdatingFromEditor = false; // Flag to prevent loop

      try {
        const schema = JSON.parse(drupalSettings.cookieBannerAdminUi.siteSettingsSchema);
        const startval = JSON.parse(textarea.value);

        const options = {
          theme: 'bootstrap3',
          iconlib: 'bootstrap',
          show_opt_in: true,
          disable_edit_json: true,
          disable_properties: true,
          disable_array_delete_all_rows: true,
          disable_array_delete_last_row: true,
          prompt_before_delete: true,
          schema: schema,
          startval: startval,
        };

        // Initialize the JSON Editor
        const editor = new JSONEditor(element, options);

        // Listen for changes in the JSON editor
        editor.on('change', function() {
          if (!isUpdatingFromEditor) {
            const updatedData = editor.getValue();
            textarea.value = JSON.stringify(updatedData, null, 2);
          }
        });

        // Listen for manual changes in the textarea
        textarea.addEventListener('input', function() {
          try {
            const updatedTextareaData = JSON.parse(textarea.value);

            // Prevent triggering the editor change event
            isUpdatingFromEditor = true;
            editor.setValue(updatedTextareaData);
            isUpdatingFromEditor = false;
          } catch (e) {
            console.error('Invalid JSON in textarea:', e);
          }
        });

      } catch (error) {
        console.error('Error fetching the schema:', error);
      }
    }
  };
})(Drupal, drupalSettings);
