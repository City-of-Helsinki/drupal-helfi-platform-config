This helfiLink plugin is largely based on CKEditor 5's
[block plugin widget tutorial](https://ckeditor.com/docs/ckeditor5/latest/framework/guides/tutorials/implementing-a-block-widget.html),
but with added documentation to facilitate better understanding of CKEditor 5 
plugin development and other minor changes.

Within `/src` are the multiple files that will be used by the build process to
become a CKEditor 5 plugin in `/build`. Technically, everything in these files
could be in a single `index.js` - the only file the MUST be present is 
`/src/index.js`. However, splitting the plugin into concern-specific files has
maintainability benefits.

## Content cleanup

The links are cleaned up on load, on paste and when the editor loses focus:

- Spaces at the end of a link text are moved after the link.
  `<a href="#">Link. </a>Text` becomes `<a href="#">Link.</a> Text`.
- Spaces at the start of a link text are moved before the link.
  `Text<a href="#"> link</a>` becomes `Text <a href="#">link</a>`.
- If there already is a space next to the link, the spaces in the link text
  are removed. `<a href="#">Link </a> text` becomes `<a href="#">Link</a> text`.
