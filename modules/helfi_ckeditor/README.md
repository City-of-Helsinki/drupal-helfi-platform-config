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

## Features

### Chat trigger
The Hel.fi CKEditor supports a data attribute for opening a chat from a button or link within content. To utilize this feature, add `<p data-chat-trigger="#trigger-listener">Open chat</p>` to the CKEditor source.

### Non-breaking spaces
The `&nbsp;` html entities are converted to regular spaces when the content is loaded to the editor or pasted into it. If content editor deliberately wants to use a non-breaking space, they can add it via keyboard shortcuts or from CKEditor "special characters" dialog. Then the `&nbsp;` is wrapped in `<span data-nbsp>` and is kept. See `assets/js/ckeditor5_plugins/helfiNbsp/README.md`.

### Content cleanup
The editor cleans up the content to avoid accessibility issues. The cleanup runs when content is loaded, pasted or when the editor loses focus.

- Two or more consecutive line breaks split the paragraph in two. `<p>Text<br><br>Another text</p>` becomes `<p>Text</p><p>Another text</p>`.
- Line breaks and spaces at the start and the end of a paragraph are removed.
- Empty paragraphs `<p></p>`, `<p> </p>` and `<p>&nbsp;</p>` are removed.
- Spaces at the start and the end of a link text are moved outside the link. `<a href="#">Link. </a>Text` becomes `<a href="#">Link.</a> Text`.
- Language codes are converted to the Drupal language codes on load and on paste. For example `fi-FI` becomes `fi` and `en-GB` becomes `en` and `sv-FI` becomes `sv`.
- Language spans in the current content language or in an unknown language, such as `X-NONE` from Word, are removed on load and on paste.
- Spans without attributes are unwrapped on load and on paste.

Pressing Shift + Enter twice in a row splits the paragraph in two, like Enter.

See `assets/js/ckeditor5_plugins/helfiNbsp/README.md` and `assets/js/ckeditor5_plugins/helfiLink/README.md`.

### Remove empty p tags from existing content
The editor cleans up empty p tags only when the content is edited. Existing content can be cleaned up with a drush command:

    drush helfi:ckeditor:remove-empty-p-tags
    drush cr

The command removes every `<p>` that contains only whitespace, `&nbsp;` or non-breaking space characters, f.e. `<p></p>`, `<p> </p>` and `<p>   &nbsp; </p>`, from every long text field. It updates the field data tables and all revisions in the revision tables. The database is updated directly, so the cache needs to be cleared afterwards.

## Known issues

### My CKEditor plugin custom elements/attributes do not appear on the "Allowed HTML tags" list
This is a [known problem](https://www.drupal.org/project/drupal/issues/3271045).
Just untick and tick the `Limit allowed HTML tags and correct faulty HTML` checkbox in the text format you're adding the plugin to. For example: `/admin/config/content/formats/manage/full_html`

### Pasting an anchor link to source removes nearly all attributes from anchor
You need to wrap the anchor to paragraph element to retain the attributes.
F.e. `<a href="#" data-hds-component="button">Test</a>` --> `<p><a href="#" data-hds-component="button">Test</a></p>`

### The `<figcaption>` and `<table>` source order in `<figure class="table">`

Altering the source order of `<figcaption>` and `<table>` elements within a `<figure class="table">` in CKEditor 5 can be a challenging task. This is mainly because it involves the behavior of the ckeditor5-table component.
The [table-layout-post-fixer](https://github.com/ckeditor/ckeditor5/blob/331d1e7a04729284efbb55937fd97a452488dc8d/packages/ckeditor5-table/src/converters/table-caption-post-fixer.ts#L59) is responsible for "fixing" the source order by moving the `<figcaption>` after the `<table>` element (or in the end of table model).
This post-fixer is executed every time there is a change event, even when a custom post-fixer attempting to address the issue has already run. This situation can potentially result in an infinite loop.

If one attempts to remove or prevent the table-layout-post-fixer, it can result in a CKEditorError: "mapping-model-position-view-parent-not-found." and a bunch of other issues. Given that the simple task of rearranging the source order of these elements is rather complex, it has been decided to handle this source order adjustment in the frontend to avoid the issues associated with post-fixers in CKEditor 5.

### Translations
The CKEditor translations are in use only for the `helfiLanguageSelector` plugin.

This is because of `Drupal.t()` not being able to translate string variables and all the language names are sent to CKEditor via the dynamic plugin config. Also, the [ckeditor5-dev-translations](https://github.com/ckeditor/ckeditor5-dev/tree/master/packages/ckeditor5-dev-translations) tool cannot append all custom plugin translations to a single file or separated files based on plugin name. Instead, it will override the previous plugins' translations.

Tip: Use `Drupal.t()` when creating new CKEditor5 plugins. If you need the CKEditor `locale.t()`, then move your plugin and the webpack config to a separate module to ease up the developing process.

### Translations are imported but not working in CKEditor
The translations for JS are handled by locale.module: `locale_js_translate()`. This function is executed when CKEditor configuration form is saved or when `locale_js_alter()` detects a placeholder file `core/modules/locale/locale.translation.js`. However, CKEditor plugins that are loaded as libraries are not included in this process as they are not associated with any render array. Consequently, the `AssetResolver::getJsAssets()` fails to locate the JS files, resulting in the absence of the `Drupal.t()` functions. This will manifest as missing translations in `window.drupalTranslations`.

To resolve this issue, you can manually invoke the `locale_js_translate()` function with an array containing your built JS files. Here's an example how to implement this solution:
```
  // Update translations manually as CKEditor plugin translations might not
  // get translated due to libraries not being loaded via render arrays.
  foreach ([
    'modules/contrib/helfi_platform_config/modules/helfi_ckeditor/assets/js/build/helfiLanguageSelector.js',
    'modules/contrib/helfi_platform_config/modules/helfi_ckeditor/assets/js/build/helfiLink.js',
    'modules/contrib/helfi_platform_config/modules/helfi_ckeditor/assets/js/build/helfiQuote.js',
  ] as $file) {
    _locale_parse_js_file($file);
  }
````

As a side note, when `Drupal.t()` function is used with a context, one should not use variable as a context string. The locale scraper won't be able to find the context in this case.

Incorrect:
```
const contextText = 'My custom context';
const variable = Drupal.t('Example', {}, {context: contextText});
````
Correct:
```
const variable = Drupal.t('Example', {}, {context: 'My custom context'});
```
