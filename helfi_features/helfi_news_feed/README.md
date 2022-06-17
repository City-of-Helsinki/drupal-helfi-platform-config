# News feed

Allows News to be listed from `Etusivu` instance via paragraphs.

News items are fetched runtime using [external_entities](https://www.drupal.org/project/external_entities) module.

## Usage

Enable the `helfi_news_feed` module.

## Overriding the source environment

The source environment (production by default) can be changed by overriding the  `helfi_news_feed.settings.source_environment` configuration.

For example:
```php
# public/sites/default/local.settings.php
$config['helfi_news_feed.settings']['source_environment'] = 'dev';
```

## How to add new fields to paragraph type

1. Create a new field through UI (Structure -> Paragraph types -> News list) and export configuration using drush.
2. Copy configuration from instance's `conf/cmi` folder to `config/install` folder and remove config hashes and uuids.
3. Provide an update hook to install/update the new configuration.
4. Add setters/getters to bundle plugin class (optional): `src/Entity/NewsFeedParagraph.php`.

## How to add new fields to remote entity

1. Add a new field to `helfi_news_feed_entity_bundle_field_info_alter()` hook.
2. Flush caches and go to Structure -> External entity types -> Helfi: News.
3. Make sure you can see the field under Manage display tab.
4. Go to Edit and follow the JSONPath documentation to map a value to field.
5. Save the entity type and export config (`drush cex`).
6. Override `config/install/external_entities.external_entity_type.helfi_news.yml` with updated configuration and provide an update hook to update existing sites.

## Enable paragraph type in a paragraph reference field

See `helfi_news_feed_install()` hook.
