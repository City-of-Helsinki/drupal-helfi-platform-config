# Update instructions

## Updating from 2.x to 3.x

### Updating configuration

- Update the module to latest 2.x version:
   - `composer update drupal/helfi_platform_config`
   - Run database updates (`drush updb`)
   - Export your configuration (`drush config:export`)
- Remove `helfi_media_map` module: `composer remove drupal/helfi_media_map` (this will be included in 3.x platform config module)
- Update the module to 3.x version: `composer require drupal/helfi_platform_config:^3.0 drupal/hdbt:^5.0 drupal/hdbt_admin:^2.0 drush/drush:^11 -W`
- Run `drush helfi:platform-config:update-config`. This will override _all_ configuration in your config export folder. Make sure to review and discard any unwanted changes before the next step
- Run `drush config:import` to import overridden configuration. The import command will most likely fail to something like:
```
In ConfigImportCommands.php line 324:

  The import failed due to the following reasons:
  Configuration <em class="placeholder">block.block.announcements</em> depends on the <em class="placeholder">helfi_announcements</em> module that will not be installed after import.
  Configuration <em class="placeholder">block.block.hdbt_subtheme_announcements</em> depends on the <em class="placeholder">helfi_announcements</em> module that will not be installed after import.
```
- This happens because we've renamed some modules providing the said configuration.
  - See `\Drupal\helfi_platform_config\Commands\MajorUpdateCommands::getModuleMap()` for a key value list of replacement module names. For example `helfi_announcements` is now called `helfi_node_announcements`.
  - You can search and replace these with `sed`, for example:`find conf/cmi -name '*.yml' -exec sed -i 's/helfi_announcements/helfi_node_announcement/g' {} +`. Repeat this steps until you've replaced all conflicting modules
- If the `drush config:import` command fails to something like:
```
In ConfigImportCommands.php line 324:

The import failed due to the following reasons:
Unexpected error during import with operation delete for core.entity_form_display.media.soundcloud.default: The &quot;soundcloud&quot; plugin does not exist. Valid plugin IDs for Drupal\media\MediaSourceManager are: hel_map, video_file, oembed:vid
eo, file, image, audio_file, helfi_chart
```
- Run `drush cache:clear` and rerun `drush config:import` command until it succeeds
- Run `drush config:export` to export changed configuration
- Review and manually discard any unwanted changes

### Updating remote environments (test/stage/prod)
- Run `drush helfi:platform-config:update-database` and re-import configuration `drush config:import`

## Updating from 1.x to 2.x

1. Install the site with existing configuration by running either `make new` or `make fresh`.
2. When the site is up and running, run `composer require drupal/helfi_platform_config:^2.0` to retrieve the new version of HELfi Platform config.
3. Run updates and export the configurations by running `make drush-updb drush-cr drush-cex`.
4. Go through configuration changes from `/conf/cmi/` and revert/modify any changes what will override customised configurations.
5. Commit the changes to your repository.
