/**
 * @file Updates the instance URL prefix of a path field.
 *
 * The prefix follows the language selected in the language field of the same
 * form. See \Drupal\helfi_platform_config\Hook\PathFieldHooks.
 */
((Drupal, once) => {
  Drupal.behaviors.helfiInstancePrefix = {
    attach(context) {
      once('helfi-instance-prefix', '[data-helfi-instance-prefixes]', context).forEach((input) => {
        const prefixes = JSON.parse(input.dataset.helfiInstancePrefixes);
        const languageField = input.form?.elements.namedItem(input.dataset.helfiLanguageField);
        const prefix = input.closest('.js-form-item')?.querySelector('[data-helfi-instance-prefix]');

        if (!languageField || !prefix) {
          return;
        }

        languageField.addEventListener('change', () => {
          prefix.textContent = prefixes[languageField.value] ?? prefixes[''];
        });
      });
    },
  };
})(Drupal, once);
