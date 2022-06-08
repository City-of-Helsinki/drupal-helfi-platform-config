# Helfi platform test content

Helfi test content module holds test content for hel.fi specific layouts, components and menu links.

**Note!** This module should not be enabled in production environment as it uses [default content](https://www.drupal.org/project/default_content) module to produce the test content. The test content is created with normal content entities, like nodes and menu links, and it will be automatically visible for anonymous users!


## How to import the test content

The content is imported when the module is enabled.

The module can be enabled from admin UI (/admin/modules) or by running the following drush command.

```
drush en -y helfi_test_content
```

## How to export changes to existing test content

Modify the nodes / menu links / etc. from the admin UI. Once the changes are saved, run the following drush command to export the data to this module.
```
drush dcem helfi_test_content
```

All content what is listed in [helfi_test_content.info.yml](helfi_test_content.info.yml) will be exported. 

## How to generate new content to this module

Create the preferred content in admin UI as you would normally do, make a note of the content ID and export the created content via drush to this module. Possible references to other entities (like paragraphs, media entities, menu links, etc.) will be created and if the referenced entity is missing from the test content, it will be created as well.

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
