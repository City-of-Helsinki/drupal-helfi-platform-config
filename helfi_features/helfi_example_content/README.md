# Hel.fi example content

## How to use

This module uses default_content module as base for the functionality.

To generate new example content or edit existing ones:

1. Make sure you have the existing content on your local. If not you should import it by enabling
the module: `drush en helfi_example_content -y`
2. Once you have the module on you should see the existing content on your local site under the 
content listing in Drupal.
3. Now all you basically need is the node id of the content that you want to export (existing or 
new) and you can export it to the module using the following drush command where you replace the 
id with the node id:
`drush dce node [id] helfi_example_content`
4. You can read more advanced guides how to use the default_content module from the contrib 
module readme that can be found under `contrib/default_content`.
