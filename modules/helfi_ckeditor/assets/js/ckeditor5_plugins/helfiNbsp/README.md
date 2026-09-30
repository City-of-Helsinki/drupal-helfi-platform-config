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
An empty paragraph between text content is kept.

## Inserting a non-breaking space

| Method                      | Mac            | PC                   |
|-----------------------------|----------------|----------------------|
| Operating system            | Option + Space | Alt + 0160           |
| Keystroke                   | -              | Ctrl + Shift + Space |
| Special characters dropdown | Text group     | Text group           |
