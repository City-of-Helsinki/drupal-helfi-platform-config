# Helfi Platform config - Translations

There are two ways to import translations to Drupal:
- [Configuration translations](https://www.drupal.org/docs/multilingual-guide/translating-configuration), which are imported to Drupal via configuration translation files
- [User interface translations](https://www.drupal.org/community/contributor-guide/reference-information/localize-drupal-org/working-with-offline/po-and), which are imported to Drupal via .po files

The user interface translations could be categorized in four different levels
1. Contributed translations from [localize.drupal.org](https://localize.drupal.org)
2. Helfi specific translations which are imported from Helfi modules and HDBT themes
3. Translations which override contrib module translations.
4. Translations missing from [localize.drupal.org](https://localize.drupal.org) (but we need them immediately)

[Learn more about contributing to Drupal translations](https://www.drupal.org/community/contributor-guide/contribution-areas/translations).

## Creating configuration translations for Helfi modules
Configuration can be translated either in Drupal admin UI or manually in to the configuration translation files.

Export the configuration translations from DB to files with `drush cex` and copy the exported files
from`/conf/cmi/language/{langcode}/` to `/module/config/optional/language/{langcode}/`.
Create and update hook for your module to import the translations.

See [Development](/documents/development.md) for more information.

### Translation context for configuration

We might want to customize some strings from contrib modules without overriding the string globally. Contrib modules can specify translation context for translatable strings in [configuration schema](https://www.drupal.org/docs/drupal-apis/configuration-api/configuration-schemametadata). However, not all contrib modules use translation contexts. To customize the context, use `hook_config_schema_info_alter()`:

```
/**
 * Implements hook_config_schema_info_alter().
 */
function my_module_config_schema_info_alter(array &$definitions) : void {
  if (isset($definitions['social_media.item.email'])) {
    $definitions['social_media.item.email']['mapping']['text']['translation context'] = 'Social media: email';
  }
}
```

## Creating custom/helfi module UI translations
Make sure the translations are imported during locale import (`drush locale:update`) by checking
the module/theme has the following information in `module_name.info.yml`. For an example, check [helfi_content.info.yml](https://github.com/City-of-Helsinki/drupal-helfi-platform-config/blob/main/helfi_features/helfi_content/helfi_content.info.yml#L46).

```
'interface translation project': module_name
'interface translation server pattern': modules/custom/module_name/translations/%language.po
```

Place your UI translations `module_name/translations/{langcode}.po` and follow the [PO file syntax](https://www.drupal.org/community/contributor-guide/reference-information/localize-drupal-org/working-with-offline/po-and-pot-files#s-syntax).

### How to create translatable variables in Drupal

#### PHP
```
$this->t('Example', [], ['context' => 'My custom context'])
```
#### Twig
```
{{ 'Example'|t({}, {'context': 'My custom context'}) }}
```
#### JS
```
const variable = Drupal.t('Example', {}, {context: 'My custom context'});
```

And the way to add the actual translation in to f.e. `fi.po` is done like so:
```
msgctxt "My custom context"
msgid "Example"
msgstr "Esimerkki"
```

To see these translation changes in an instance, run in container shell:
```
drush locale:check && drush locale:update
```
And then flush all caches from top left drupal admin menu under "Druplicon".

## Creating UI translation overrides for contrib modules

The translation overrides are imported to Drupal instance with [PO importer](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/documentation/po-importer.md) and translation overrides lives in `helfi_platform_config/translations/override/{langcode}.po`.

Add the original module name of overridden translation as a comment, so that it's easier to locate the original UI text.

## Creating new UI translations for contrib modules

The contrib module translations are not complete, thus we need to have a way to add translations for the contrib modules in Hel.fi context.
It is highly recommended to contribute back to the Drupal community and add the translations to [localize.drupal.org](https://localize.drupal.org/translate/languages/fi/translate?project=drupal).

We do usually have the urgency to add translations right away and cannot wait for the
processes. In these situations we can add translations in to `helfi_platform_config/translations/new/{langcode}.po`.
Add the module name as a comment, so that it's easier to contribute back to drupal.org.
