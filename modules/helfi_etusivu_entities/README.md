# Remote entities

Remote entities module allows fetching announcements from `Etusivu`-instance.
It utilizes `json-api` and `external_entities`-module to transfer the data between instances.

## Language support

If current language is not in [default languages](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/documentation/default-languages.md), remote entities use fallback language. Alt languages show local announcements in current language (if translated) and in English.

## How to set up locally

Local setup requires Etusivu-instance to be up and running with some relevant data created to it.

## Fixture data

Remote announcements are read from the `fixtures` folder instead of the Etusivu instance when the `use_mock_responses` feature of `helfi_api_base` is enabled. The fixtures are used for test content and visual regression tests.

The `helfi_test_content` module enables the feature and the remote announcements of the announcements block when it is installed. To enable or disable the feature manually, run:

```
drush cset -y helfi_api_base.features use_mock_responses 1
drush cset -y helfi_api_base.features use_mock_responses 0
```

# Cache

Remote entities are cached with custom cache tags. These tags are cleared when any entities are updated using [PubSub](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/documentation/pubsub-messaging.md) by etusivu instance. See [`helfi_etusivu` -module](https://github.com/City-of-Helsinki/drupal-helfi-etusivu/tree/dev/public/modules/custom/helfi_etusivu).
