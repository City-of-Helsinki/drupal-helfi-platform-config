# Helfi platform TPR test content

Helfi TPR test content module holds test content for hel.fi TPR specific layouts.

**Note!** This module should not be enabled in production environment as it uses [default content](https://www.drupal.org/project/default_content) module to produce the test content. The test content is created with TPR content entities and it will be automatically visible for anonymous users!


## How to import the test content

The content is imported when the module is enabled. The module will be enabled automatically when helfi_test_content module is being installed.


## How to export changes to existing test content

Modify the TPR Unit or TPR service / menu links / etc. from the admin UI. Once the changes are saved, run the following drush command to export the data to this module.
```
drush dcem helfi_tpr_test_content
```

All content what is listed in [helfi_tpr_test_content.info.yml](helfi_tpr_test_content.info.yml) will be exported. 

**Note!**
When exporting TPR Service and TPR Unit content, **do not remove or commit the entity id field changes**, as the entity types relies on predefined entity ID. 
