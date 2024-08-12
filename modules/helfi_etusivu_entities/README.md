# Remote entities

Remote entities module allows fetching announcements from `Etusivu`-instance.
It utilizes `json-api` and `external_entities`-module to transfer the data between instances.

## How to set up locally

Local setup requires Etusivu-instance to be up and running with some relevant data created to it.

# Cache

Remote entities are cached with custom cache tags. These tags are cleared when any entities are updated using [PubSub](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/documentation/pubsub-messaging.md) by etusivu instance. See [`helfi_etusivu` -module](https://github.com/City-of-Helsinki/drupal-helfi-etusivu/tree/dev/public/modules/custom/helfi_etusivu).
