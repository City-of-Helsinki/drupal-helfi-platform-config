# Update instructions

## Switching from the EU Cookie Compliance Module to the HDBT cookie banner Module

The HDBT cookie banner module brings support for the [Helsinki Design System (HDS)](https://github.com/City-of-Helsinki/helsinki-design-system) cookie banner.

### Minimum requirements to use the HDBT cookie banner module

The HDBT cookie banner is included in the following versions of the Helfi packages:

| Module                | Minimum Version                                                                                | Description                                                      |
|-----------------------|------------------------------------------------------------------------------------------------|------------------------------------------------------------------|
| helfi_platform_config | [^4.6.23](https://github.com/City-of-Helsinki/drupal-helfi-platform-config/releases/tag/4.6.23) | Includes the HDBT cookie banner module.                          |
| hdbt                  | [^6.7.22](https://github.com/City-of-Helsinki/drupal-hdbt/releases/tag/6.7.22)                 | Includes updates to component templates, styles, and JavaScript. |
| hdbt_admin            | [^3.2.7](https://github.com/City-of-Helsinki/drupal-hdbt-admin/releases/tag/3.2.7)              | Supports the cookie banner in the admin theme.                   |

### How to switch the cookie banner configuration

Ensure your local environment is up and running on the latest development branch.

- Update the Helfi packages to the latest versions:
  - `composer update drupal/helfi_platform_config drupal/hdbt drupal/hdbt_admin -W`
- Uninstall the EU Cookie Compliance module. This will also uninstall the Helfi EU Cookie Compliance module:
  - `drush pmu -y eu_cookie_compliance`
- Install the HDBT cookie banner module:
  - `drush en -y hdbt_cookie_banner`
- Export your configuration:
  - `drush cex -y`
- Review the updated configurations and commit the changes to your repository.

### Setting Up the JSON for Cookie Information

- Log in to Drupal and navigate to the `/admin/structure/hdbt-cookie-banner` page.
- The site settings contain the cookie information in JSON format. This data is saved as a configuration object but is not saved to `conf/cmi/`, so you need to configure the JSON manually:
  - Enable `Use instance-specific cookie settings` and scroll down to the `Site settings` textarea.
  - Copy the contents from [Site settings](https://github.com/City-of-Helsinki/drupal-helfi-platform-config/blob/main/modules/hdbt_cookie_banner/assets/json/siteSettingsTemplate.json) to the `Site settings` text area. This JSON provides basic cookie information for Helfi Drupal sites.
  - Go through each variable in the cookie information and adjust the values to match your needs. Some values are labeled such as `Change me`, `change-me` or `change-me.hel.fi` which you need to modify to avoid cookie conflicts.
  - Save the changes and verify that the cookie banner is functioning as expected.
  - Adjust the cookie settings as needed and save the changes. Verify that the updates are applied correctly.
- When deploying to test, staging, or production environments, manually copy the `Site settings` JSON to the textarea.


## Updating from 3.x to 4.x

Helfi platform config 4.x brings support to Drupal 10.

### Updating configuration

Get your local environment up and running.

- Update the Drupal packages and helfi packages to latest versions.
  - `composer require drupal/core:^10.1 drupal/core-composer-scaffold:^10.1 drush/drush:^12 drupal/helfi_platform_config:^4.0 drupal/hdbt:^6.0 drupal/hdbt_admin:^3.0 -W`
   - If the installation fails due to conflicting problems, like: `drupal/raven is locked to version 4.0.16 and an update of this package was not requested.`, then include the `drupal/raven:^4.0` to the requirements, like so: `composer require drupal/core:^10.1 drupal/core-composer-scaffold:^10.1 drush/drush:^12 drupal/helfi_platform_config:^4.0 drupal/hdbt:^6.0 drupal/hdbt_admin:^3.0 drupal/raven:^4.0 -W`
  - Update the core-dev package separately, as it's dev-dependency: `composer require drupal/core-dev:^10.1 --dev -W`
- After the packages have been installed, run the database updates
  - `make drush-updb` or `drush updb`
- Export your configuration
  - `make drush-cex` or `drush cex -y`
- Go through the changed configurations and manually discard any unwanted changes.

### Updating remote environments (test/stage/prod)

There shouldn't be any problems after deployment, but if any occurs, cache clear  and running an extra drush deploy command should fix the problems.
- `drush cr; drush deploy`


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
- If the configuration import fails, run `drush cache:rebuild` and `drush config:import` until the import is succesful

## Updating from 1.x to 2.x

1. Install the site with existing configuration by running either `make new` or `make fresh`.
2. When the site is up and running, run `composer require drupal/helfi_platform_config:^2.0` to retrieve the new version of HELfi Platform config.
3. Run updates and export the configurations by running `make drush-updb drush-cr drush-cex`.
4. Go through configuration changes from `/conf/cmi/` and revert/modify any changes what will override customised configurations.
5. Commit the changes to your repository.
