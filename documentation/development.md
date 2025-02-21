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

Permissions should be defined in module's `hook_platform_config_grant_permissions()` hook:

```php
/**
 * Implements hook_platform_config_grant_permissions().
 */
function helfi_node_news_article_platform_config_grant_permissions() : array {
  return [
    'admin' => [
      'view news_article revisions',
    ],
    'content_producer' => [
      'view news_article revisions',
    ],
  ];
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
To install blocks in your module, you should define them in the module's `.module` file and use the `BlockInstaller` service to handle block installations.

Usually, block configurations are installed using YAML files located in `./config/optional/block.block.block_name.yml`, similar to how it is done in install profiles. However, in our case, using this approach would result in unnecessary configuration reverts when the `helfi_platform_config.config_update_helper` service is used. With `BlockInstaller` service, you can install the block configurations for multiple themes without having the unnecessary configuration reverts during module updates and without duplicated configuration files under config folders.

Define the block as follows:
```php
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
```php
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

### Update module configuration

Run: `drush helfi:platform-config:update`. This will re-import all configuration and trigger necessary hooks, such as `hook_platform_config_grant_permissions()` to update user permissions or `hook_helfi_paragraph_types()` to update Paragraph fields.

To update individual modules, run: `drush helfi:platform-config:update {module name}`.

#### Rewrite configuration

The `helfi_platform_config.config_update_helper` invokes `hook_rewrite_config_update`, which allows custom modules to react to config re-importing.

##### In this example we would want to override Text paragraph label with a configuration found in my_module.

To trigger the `hook_rewrite_config_update`, implement the hook to your `my_module.module`:
```php
function my_module_rewrite_config_update(string $module, Drupal\config_rewrite\ConfigRewriterInterface $configRewriter): void {
  if ($module === 'helfi_paragraphs_text') {
    // Rewrite helfi_paragraphs_text configuration.
    $configRewriter->rewriteModuleConfig('my_module');
  }
}
```
This hook will trigger when `\Drupal::service('helfi_platform_config.config_update_helper')->update('helfi_paragraphs_text');` is run, and it will search for configurations in `my_module/config/rewrite/` folder.

To override configurations for your Drupal instance, follow the instructions found in [Rewrite module project page](https://www.drupal.org/project/config_rewrite).

In our example, the label change would be implemented in a configuration file `/public/modules/custom/my_module/config/rewrite/paragraphs.paragraphs_type.text.yml`:
```yml
label: Text (override)
```
The label change for the Finnish translation would be implemented in a configuration file `/public/modules/custom/my_module/config/rewrite/language/fi/paragraphs.paragraphs_type.text.yml`
```yml
label: Teksti (ylikirjoitettu)
```

#### How to fix recursive errors during site install?

In some cases there can be recursive errors during site install which will slow down the site installation process.
Here are two examples and instructions how to fix the issues.

##### Deleted and replaced configuration entity
```
 [error]  Deleted and replaced configuration entity "field.field.paragraph.event_list.field_filter_keywords"
```
This error occurs when the configuration translation has been added to `config/install/language/[langcode]/` folder. To fix the error, move the translation to `module/config/optional/language/[langcode]/` folder.

##### Unexpected error during import with operation create
```
 [error]  Unexpected error during import with operation create for core.entity_form_display.helfi_news_groups.helfi_news_groups.default: The "helfi_news_groups" entity type does not exist.
```
This error occurs when the entity type is not installed yet, but the configuration for its form display is being imported. Drupal should import the configuration dependencies before this form display configuration, but in some cases it does not. To fix the error, enforce the dependency on the configuration dependencies. See example: [core.entity_form_display.helfi_news_groups.helfi_news_groups.default](https://github.com/City-of-Helsinki/drupal-helfi-platform-config/blob/893e2ae5981898d409a67f3ddb879e6a771a3217/modules/helfi_paragraphs_news_list/config/install/core.entity_form_display.helfi_news_groups.helfi_news_groups.default.yml#L7).
The error gets fixed once the configurations have been added to Drupal repository's `conf/cmi/` folder.

## Tokens

Helfi platform config implements `hook_tokens()`. With the help of `helfi_platform_config.og_image_manager` service, it provides `[*:shareable-image]` token. Modules may implement services that handle this token for their entity types.

Modules that use this system should still implement `hook_tokens_info` to provide information about the implemented token.

### Defining image builder service

Add a new service:

```yml
# yourmodule/yourmodule.services.yml
  yourmodule.og_image.your_entity_type:
    class: Drupal\yourmodule\Token\YourEntityImageBuilder
    arguments: []
    tags:
      - { name: helfi_platform_config.og_image_builder, priority: 100 }
```

```php
# yourmodule/src/Token/YourEntityImageBuilder.php
<?php

declare(strict_types=1);

namespace Drupal\yourmodule\Token;

use Drupal\helfi_platform_config\Token\OGImageBuilderInterface;

/**
 * Handles token hooks.
 */
final class YourEntityImageBuilder implements OGImageBuilderInterface {

  /**
   * {@inheritDoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $entity instanceof YourEntity;
  }

  /**
   * {@inheritDoc}
   */
  public function buildUrl(EntityInterface $entity): ?string {
    assert($entity instanceof YourEntity);
    return $entity->field_image->entity->getFileUri();
  }

}
```


## First paragraph grey alter

There are some paragraphs that we want to "blend" with the hero-block if they are directly after the hero such as
searches. Good example of this kind of search is `unit_search`. The grey background should continue from hero to the
paragraph seamlessly and for paragraphs that are usable on all instances this is done in the `helfi_platform_config`.
These paragraphs can be found listed in the `$paragraphs_with_grey_bg` variable in `HeroBlock.php`.

There can be paragraphs that we want to function this way, but they are instance specific. For them to be able to
function the same way you need to use the `first_paragraph_grey_alter` on that instance that uses the paragraph.

Here is an example on how this is done in front page instance (helfi_etusivu custom module, helfi_etusivu.module file):

```php
/**
* Implements hook_first_paragraph_grey_alter().
*/
function helfi_etusivu_first_paragraph_grey_alter(array &$paragraphs): void {
  $paragraphs[] = 'news_archive';
}
```
