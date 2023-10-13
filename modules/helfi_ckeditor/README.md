# Hel.fi CKEditor

Requirements for developing:
- [NodeJS](https://nodejs.org/en/)
- [NPM](https://npmjs.com/)
- optional [NVM](https://github.com/nvm-sh/nvm)

## Commands

| Command       | Description                                                                       |
|---------------|-----------------------------------------------------------------------------------|
| nvm use       | Uses correct Node version chosen for the theme compiler.                          |
| npm i         | Install dependencies and link local packages.                                     |
| npm run watch | Compile styles and js for development environment. and watch file changes.        |
| npm run build | Build packages for production. Minify CSS/JS.                                     |

Set up the developing environment by running

    nvm use
    npm i

Explanations for commands.
- `nvm use` : Install and use the correct version of Node.
- `npm i` : As stated above; Install dependencies and link local packages.

Related files.
- `.nvmrc` : Defines the node version used to compile the theme.
- `package.json and package-lock.json` : Defines the node modules and scripts for compiling the theme.
- `webpack.config.js` : Configuration file for the webpack-tool

## Known issues

### My CKEditor plugin custom elements/attributes do not appear on the "Allowed HTML tags" list
This is a [known problem](https://www.drupal.org/project/drupal/issues/3271045).
Just untick and tick the `Limit allowed HTML tags and correct faulty HTML` checkbox in the text format you're adding the plugin to. For example: `/admin/config/content/formats/manage/full_html`

### Pasting an anchor link to source removes nearly all attributes from anchor
You need to wrap the anchor to paragraph element to retain the attributes.
F.e. `<a href="#" data-hds-component="button">Test</a>` --> `<p><a href="#" data-hds-component="button">Test</a></p>`

### Link/Button "external link" icon is missing in CKEditor
The `data-is-external=true` attribute is not rendered in CKEditor, because the actual rendering of this attribute happens when the markup is being rendered in frontend.
See: [Helfi link converter](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/src/Plugin/Filter/LinkConverter.php)
#### What if we really, really want to render this icon in CKEditor?
It can be done by doing an ajax call during each link conversion to a custom endpoint which will ask the [[InternalDomainResolver](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/src/Link/InternalDomainResolver.php)] if the link is external or not. At the time this felt like a big task to do for such a simple indicator.

### Translations
The CKEditor translations are in use only for the `helfiLanguageSelector` plugin.

This is because of `Drupal.t()` not being able to translate string variables and all the language names are sent to CKEditor via the dynamic plugin config. Also, the [ckeditor5-dev-translations](https://github.com/ckeditor/ckeditor5-dev/tree/master/packages/ckeditor5-dev-translations) tool cannot append all custom plugin translations to a single file or separated files based on plugin name. Instead, it will override the previous plugins' translations.

Tip: Use `Drupal.t()` when creating new CKEditor5 plugins. If you need the CKEditor `locale.t()`, then move your plugin and the webpack config to a separate module to ease up the developing process.

### Translations are imported but not working in CKEditor
The translations for JS are handled by locale.module: `locale_js_translate()`. This function is executed when CKEditor configuration form is saved or when `locale_js_alter()` detects a placeholder file `core/modules/locale/locale.translation.js`. However, CKEditor plugins that are loaded as libraries are not included in this process as they are not associated with any render array. Consequently, the `AssetResolver::getJsAssets()` fails to locate the JS files, resulting in the absence of the `Drupal.t()` functions. This will manifest as missing translations in `window.drupalTranslations`.

To resolve this issue, you can manually invoke the `locale_js_translate()` function with an array containing your built JS files. Refer to `helfi_ckeditor.install` --> `helfi_ckeditor_update_9004()` for an example of how to implement this solution.
