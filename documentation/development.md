# Development

## Installation

Install the module with VCS information using composer:

`composer reinstall drupal/helfi_platform_config --prefer-source`

## Submodules

Submodules should be split by entity type/bundle or by feature. For example:

- `helfi_node_announcement`: Contains `Announcement` Node type
- `helfi_node_page`: Contains `Page` Node type
- `helfi_tpr_config`: Contains Platform specific TPR configuration, like paragraph fields used to enrich TPR entities etc.
- `helfi_media`: Contains default Media types
- `helfi_media_remote_video`: Contains `remote_video` Media type.

### Install hooks

All `hook_install()` functions must be idempotent, meaning it should be possible to call them multiple times without changing the result. For example:

```php
function mymodule_install() : void {
  // Create 'admin' role if it does not exist yet.
  if (!Role::load('admin')) {
    Role::create('admin')->save();
  }
}
```

### Field storage configuration

If the same field storage is used in multiple different modules (like `field_lower_content` paragraph reference field used in most of our content types), the field storage config must be placed in `helfi_base_content` module's `config/install/` folder.

When using nested paragraphs, like `list_of_links` paragraph type that references `list_of_links_item` paragraph type, the field storage seems to require the paragraph type to be added as `enforced` dependency.

```yaml
# modules/helfi_paragraphs_list_of_links/config/install/field.storage.paragraph.field_list_of_links_links.yml
dependencies:
  enforced:
    config:
      - paragraphs.paragraphs_type.list_of_links
```

## Drupal permissions

Permissions should be defined in module's `.install` file and the function should be called in `hook_install()` hook:

```php
function mymodule_grant_permissions() : void {
  $permissions = [
    'admin' => [
      'access content',
    ],
    'anonymous' => [
      'access content',
    ],
  ];
  helfi_platform_config_grant_permissions($permissions);
}

/**
 * Implements hook_install().
 */
function mymodule_install() : void {
  mymodule_grant_permissions();
}
```

## Paragraph types

All paragraph reference fields are stripped off `target_bundles` field configuration, so we don't have to add hard dependencies between modules.

The target bundles are overridden every time field configuration is saved (by `helfi_platform_config_field_config_presave()` hook). The target bundles list is compiled from modules implementing `hook_helfi_paragraph_types()` hook.

The module using paragraphs should define a `hook_helfi_paragraph_types()` hook that returns an array of `\Drupal\helfi_platform_config\DTO\ParagraphTypeCollection` objects. See the class for more documentation and [helfi_node_page](/modules/helfi_node_page/helfi_node_page.module) module for an example implementation.

Projects using custom paragraph types must implement the `hook_helfi_paragraph_types()` hook that contains project specific paragraph types, for example:

```php
# public/modules/custom/helfi_sote/helfi_sote.module:

/**
 * Implements hook_helfi_paragraph_types().
 */
function helfi_sote_helfi_paragraph_types() : array {
  $entities = [
    'tpr_unit' => [
      'tpr_unit' => [
        'field_lower_content' => [
          'sote_specific_paragraph_type',
        ],
      ],
    ],
    'node' => [
      'page' => [
        'field_content' => [
          'sote_specific_paragraph_type'
        ],
      ],
    ],
  ];

  $enabled = [];
  foreach ($entities as $entityTypeId => $bundles) {
    foreach ($bundles as $bundle => $fields) {
      foreach ($fields as $field => $paragraphTypes) {
        foreach ($paragraphTypes as $paragraphType) {
          $enabled[] = new ParagraphTypeCollection($entityTypeId, $bundle, $field, $paragraphType);
        }
      }
    }
  }
  return $enabled;
}
```

## Blocks

To install blocks in your module, you should define them in the module's `.module` file and use the BlockInstaller service to handle block installations.

Usually, block configurations are installed using YAML files located in `./config/optional/block.block.block_name.yml`, similar to how it is done in install profiles. However, in our case, using this approach would result in unnecessary configuration reverts when the `helfi_platform_config.config_update_helper` service is used.

Define the block as follows:
```
/**
 * Gets the block configurations.
 *
 * @return array[]
 *   The block configurations.
 */
function my_example_get_block_configurations(string $theme) : array {
  return [
    'block' => [
      'id' => 'my_example_block',
      'plugin' => 'my_example_block_plugin',
      'settings' => [
        'label' => 'My example',
        'label_display' => TRUE,
      ],
      'provider' => 'my_example',
      'translations' => [
        'fi' => 'Minun esimerkki',
        'sv' => 'Mitt exempel',
      ],
    ],
    'variations' => [
      [
        'theme' => 'hdbt',
        'region' => 'content',
      ],
      [
        'theme' => 'stark',
        'region' => 'content',
      ],
    ],
  ];
```

And install the blocks in the modules install/update hooks:
```
/**
 * Implements hook_install().
 */
function my_example_install($is_syncing) : void {
  // Do not perform following steps if the module is being installed as part
  // of a configuration import.
  if ($is_syncing) {
    return;
  }

  /** @var Drupal\helfi_platform_config\Helper\BlockInstaller $block_installer */
  $block_installer = Drupal::service('helfi_platform_config.helper.block_installer');

  /** @var \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler */
  $theme_handler = \Drupal::service('theme_handler');

  if (!str_starts_with($theme_handler->getDefault(), 'hdbt')) {
    return;
  }

  $theme = $theme_handler->getDefault();
  $block_config = my_example_get_block_configurations($theme);
  ['block' => $block, 'variations' => $variations] = $block_config;
  $block_installer->install($block, $variations);
}
```
See examples in `helfi_global_announcement` and `helfi_tpr_config` modules.

## Updating existing configuration

### Cleaning up YAML files automatically

You can use `drush helfi:clean-yml {path}` command to automatically "clean up" the modified YAML files:

- `drush helfi:clean-yml public/modules/contrib/helfi_platform_config`

The command will:

- Scan all yml files and strip off the `uuid` field
- Remove `target_bundles` from Paragraph reference fields (see [Paragraph types](#paragraph-types))

See https://github.com/City-of-Helsinki/drupal-tools/blob/main/HelperCommands.php for more up-to-date information.

### Update permissions

To update permissions, add the new/changed permission in modules' `mymodule_grant_permission()` and call the function in `hook_update_N()` hook:

```php
function mymodule_update_9001(): void {
  mymodule_grant_permissions();
}
```

### Update all configuration

Use `helfi_platform_config.config_update_helper` service to replace existing configuration:

```php
function helfi_media_update_9001() : void {
  // Re-import 'helfi_media' configuration.
  \Drupal::service('helfi_platform_config.config_update_helper')
    ->update('helfi_media');
}
```
The update hook above will re-import all configuration from `helfi_media` module's `config/install` and `config/rewrite` folders and run necessary post-update hooks.
