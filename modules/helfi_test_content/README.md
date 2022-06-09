# Helfi platform test content

Helfi test content module holds test content for hel.fi specific layouts, components and menu links.

**Note!** This module should not be enabled in production environment as it uses [default content](https://www.drupal.org/project/default_content) module to produce the test content. The test content is created with normal content entities, like nodes and menu links, and it will be automatically visible for anonymous users!


## How to import the test content

The content is imported when the module is enabled.

The module can be enabled from admin UI (/admin/modules) or by running the following drush command.

```
drush en -y helfi_test_content
```

When the module is already enabled and the content should be re-imported, it can be done with following drush command.

```
drush dcim helfi_test_content --update-existing
```

## How to export the test content

Modify the nodes / menu links / etc. from the admin UI. Once the changes are saved, run the following drush command to export the data to this module.
```
drush dcem helfi_test_content
```

All content what is listed in [helfi_test_content.info.yml](helfi_test_content.info.yml) will be exported.

**Note!** As always with exported configurations and/or content, go through the exported changes in the .yml files and remove the unwanted ones before committing the code. 

## How to generate new test content

Create the preferred content in admin UI as you would normally do, make a note of the content ID and export the created content via drush to this module. Possible references to other entities (like paragraphs, media entities, etc.) will be created and if the referenced entity is missing from the test content, it will be created as well.

### Examples

#### Nodes
1. Create a node of any type, fill in the desired content and save it.
2. Retrieve the ID of the content.
   1. Either check from the `/admin/content` list by hovering the edit link or go and edit the node and get the ID from the URL `/node/##/edit`
3. Run the following drush command. The `1` is the ID of the node. 
```
drush dcer node 1 --folder=/app/public/modules/contrib/helfi_platform_config/modules/helfi_test_content/content
```
4. Add the new content UUID to [helfi_test_content.info.yml](helfi_test_content.info.yml) under appropriate entity.

#### Menu links
1. Create a menu link.
2. Retrieve the ID of the menu link.
   1. Either check from the `/admin/structure/menu/manage/main` list by hovering the edit link or go and edit the menu link and get the ID from the URL `/admin/structure/menu/item/##/edit`
3. Run the following drush command. The `22` is the ID of the node. 
```
drush dcer menu_link_content 22 --folder=/app/public/modules/contrib/helfi_platform_config/modules/helfi_test_content/content
```
4. Add the new content UUID to [helfi_test_content.info.yml](helfi_test_content.info.yml) under appropriate entity.

#### Other content

Content can be exported with the drush command like so:
```
drush dcer [entity type] [id] --folder=/app/public/modules/contrib/helfi_platform_config/modules/helfi_test_content/content
```

## How to delete the test content

The test content can be deleted manually. [There is a fix on the way](https://www.drupal.org/project/default_content/issues/3282547). 

## Known bugs

### Path aliases
There seems to be a problem of nodes losing their paths if pathauto is enabled for the node. To fix this problem, make sure the paths are not auto generated. For example in a node.yml file check that the pathauto variable is set to 0. 
```
  path:
    -
      alias: /dc-helfi-platform-test-content/dc-components/dc-component-list-of-plans
      langcode: en
      pathauto: 0
```

### Exporting nodes with drush dcer won't create the menu item

This is actually not a bug, because the reference is from menu item --> node.

To fix the problem, the menu item should be imported instead of the node.
For example: 
- The node is called `DC: Navigation` and it's added to menu with the same name. 
- We need to retrieve the menu link ID to export it and it's references.
  - Either check from the /admin/structure/menu/manage/main list by hovering the edit link or go and edit the menu link and get the ID from the URL `/admin/structure/menu/item/##/edit`. In our example it's `/admin/structure/menu/item/10/edit` 
- Then it's as simple as exporting the menu item with references
```
drush dcer menu_link_content 10 --folder=/app/public/modules/contrib/helfi_platform_config/modules/helfi_test_content/content
```
