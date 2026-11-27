# Helfi Nbsp

The plugin converts non-breaking spaces to regular spaces.

## How it works

The `&nbsp;` html entities are converted to regular spaces when the content is
loaded to the editor or pasted into it. If the user adds a non-breaking space
with a keyboard shortcut or the nbsp button, it is wrapped with
`<span data-nbsp>`, so the plugin does not convert it. The wrapped non-breaking
space is styled to be visible in the editing view.

CKEditor writes a `&nbsp;` when user creates a new paragraph. If these
paragraphs exist at the start or at the end of the content, they are removed.
The paragraphs are cleaned up on load, on paste and when the editor loses focus:

- Two or more consecutive <br> tags split the paragraph in two.
- <br> tags and spaces at the start and the end of a paragraph are removed.
- Empty paragraphs are removed.

Pressing Shift + Enter twice in a row splits the paragraph in two, like Enter. The <br> tag and the spaces next to the split are removed, so no `<br>` or
`&nbsp;` is left at the edges of the paragraphs.

## Inserting a non-breaking space

| Method                      | Mac            | PC                   |
|-----------------------------|----------------|----------------------|
| Operating system            | Option + Space | Alt + 0160           |
| Keystroke                   | -              | Ctrl + Shift + Space |
| Special characters dropdown | Text group     | Text group           |
