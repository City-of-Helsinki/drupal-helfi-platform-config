This helfiLanguageSelector plugin is largely based on CKEditor 5's
[block plugin widget tutorial](https://ckeditor.com/docs/ckeditor5/latest/framework/guides/tutorials/implementing-a-block-widget.html), but with added documentation to facilitate better understanding 
of CKEditor 5 plugin development and other minor changes.

Within `/src` are the multiple files that will be used by the build process to
become a CKEditor 5 plugin in `/build`. Technically, everything in these files
could be in a single `index.js` - the only file the MUST be present is 
`/src/index.js`. However, splitting the plugin into concern-specific files has
maintainability benefits.

## Content cleanup

The language spans are cleaned up on load and on paste:

- The language code is converted to a code in the Drupal language list. The
  full code is matched first and then the code without the region. `fi-FI`
  becomes `fi`, `SV-fi` becomes `sv` and `zh-Hant` becomes `zh-hant`.
- The text direction is taken from the language list.
  `<span lang="ar-SA">` becomes `<span lang="ar" dir="rtl">`.
- Spans in the current content language are removed. On a Finnish page
  `<span lang="FI">sauna</span>` and `<span lang="fi-FI">sauna</span>` become
  `sauna`.
- Spans in an unknown language are removed. `<span lang="X-NONE">text</span>`
  becomes `text`.
- Spans without attributes are unwrapped. `<span>text</span>` becomes `text`.
