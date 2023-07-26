# Hel.fi CKEditor

## Known issues

### Translations 
The CKEditor translations are in use only for the `helfiLanguageSelector` plugin. 

This is because of `Drupal.t()` not being able to translate string variables and all the language names are sent to CKEditor via the dynamic plugin config. Also, the [ckeditor5-dev-translations](https://github.com/ckeditor/ckeditor5-dev/tree/master/packages/ckeditor5-dev-translations) tool cannot append all custom plugin translations to a single file or separated files based on plugin name. Instead, it will override the previous plugins' translations. 

Tip: Use `Drupal.t()` when creating new CKEditor5 plugins. If you need the CKEditor `locale.t()`, then move your plugin and the webpack config to a separate module to ease up the developing process.
