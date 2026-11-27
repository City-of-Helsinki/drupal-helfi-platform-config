# Search API

## Custom datatypes

A custom datatype needs to be marked as supported by Elasticsearch Connector using an event subscriber for `Drupal\elasticsearch_connector\Event\SupportsDataTypeEvent`. Without this step the `fallback_type` from the datatype annotation is used instead (defaults to `string`).

See for example:

* Datatype: `Drupal\helfi_react_search\Plugin\search_api\data_type\CommaSeparatedStringDataType`.
* Event subscriber: `Drupal\helfi_react_search\EventSubscriber\SearchApiSubscriber::alterSupportsDataType()`.

## Custom processors with `object`-type properties

`object`-type property is supported by Elasticsearch, but is not mapped for Search API by default, causing any such properties to stay hidden when adding new fields in Search API field configuration. The mapping is added via an event subscriber for `Drupal\search_api\Event\SearchApiEvents::MAPPING_FIELD_TYPES`.

This mapping exists in `helfi_react_search`, so no need to add it again if that module is in use.

See for example:

* Processor: `Drupal\helfi_react_search\Plugin\search_api\processor\ChannelsForService`
* Event subscriber: `Drupal\helfi_react_search\EventSubscriber\SearchApiSubscriber::mapFieldTypes()`

## Multisite index

Some indexes are shared between multiple sites and for that we need a way to avoid duplicate item id values, especially when using the default content entity datasource. There is a custom option "Multisite index" in index configuration, and when enabled a site specific prefix is added to all item id values.

The feature utilises Elasticsearch Connector event subscribers to add the prefix value before index and delete queries are executed. As a safety mechanism the id value is also altered back to what Search API is using in its own tracking table when processing search query results.

### Good to know

* The id prefix should have no side effects when elasticsearch queries are executed directly from React applications or custom code.
* A use case where multisite index is used as source in Views and results are rendered using the matching content entities might have unexpected results (results from other sites might cause a totally unrelated local entity to be rendered if a matching entity type & id combination is found). If such a setup is needed, a new index field with some kind of site identification should be added and then used to filter only results from current site.

## Shared indexes

Some indexes are written to by several instances. They are listed in `Drupal\helfi_platform_config\SearchAPI\RestrictedSearchIndex` with their main instance and their site field, which holds the name of the instance that indexed the document:

| Index | Main instance | Site field |
|-------|---------------|------------|
| `embeddings` | etusivu | `instance` |
| `suggestions` | etusivu | `parent_instance` |
| `hyte` | asuminen | `instance` |

`Drupal\helfi_platform_config\SearchAPI\RestrictedElasticSearchBackend` replaces the `elasticsearch` backend plugin through `hook_search_api_backend_info_alter()`. For a shared index:

* Clearing (`drush sapi-c`, the Search API admin UI, or code) deletes only the active instance's documents, with a delete-by-query on the site field. It works the same on every instance and never touches the documents of another instance.
  A warning explains this and how to delete every instance's documents: disable and enable the index on its main instance. On the command line, such as `drush sapi-c`, the warning is logged, which Drush prints. In the admin UI it is shown as a message.
* Only the main instance creates, deletes or rebuilds the Elasticsearch index, or changes its settings.
* Other instances may add field mappings. This keeps their documents from being mapped dynamically when they deploy a new field before the main instance.
* Documents are indexed and deleted as usual.

To add a shared index, add it to `RestrictedSearchIndex` and make sure it has a `string` site field, for example a `custom_value` field with the value `[site:helfi-current-instance]`.

### Rebuilds

The main instance creates the Elasticsearch index anew when:

* a breaking field change (a removed field or a changed type) needs a rebuild,
* the index is enabled again after being disabled, or moved to another server,
* the index is missing from Elasticsearch when the index configuration is saved.

Creating the index again deletes the documents of every instance. So the main instance marks its own items for reindexing and sends an [Azure PubSub message](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/documentation/pubsub-messaging.md):

```json
{"search_api_reindex": {"index": "embeddings", "instance": "etusivu"}}
```

`Drupal\helfi_platform_config\EventSubscriber\SearchApiReindexSubscriber` receives it on the other instances. If the index is shared and enabled there, it marks all items for reindexing (same as `drush search-api:reset-tracker`). The items are then indexed on the next indexing run.

Clearing a shared index sends nothing, because it never touches the documents of other instances.

Good to know:

* Receiving requires the `drush helfi:azure:pubsub-listen` worker and the `pubsub` vault item. Messages are not stored: an instance whose worker is not connected misses the message.
* A breaking field change also marks the items for reindexing on every other instance when it imports the same index configuration. This covers instances that were deploying, without a connected worker, when the main instance sent the message.
* If sending fails, a warning is logged and the rebuild still succeeds. The other instances then have to run `drush sapi-r <index>` manually.
* Disabling or deleting the index on the main instance deletes the Elasticsearch index without a message. The other instances are asked to reindex once the main instance creates the index again.

## Path alias updates

Quite a few indexes contain fields with url aliases, and usually this data stays up-to-date via regular content updates that trigger reindexing. However additional update hooks are needed to cover path alias operations that happen outside the content entity update context, like:

* Drupal bulk generate tool in `/admin/config/search/path/update_bulk`
* Update hook batch updates (like in `helfi_platform_config_update_11009()`)

See for example:

* Generic hooks that cover regular routed content entities: `Drupal\helfi_platform_config\Hook\PathAliasHooks`
* Special handling for suggested topics: `Drupal\helfi_recommendations\Hook\PathAliasHooks`
  * `suggestions`-index contains `suggested_topics`-entities, which are entity reference field targets from content entities (`node`, `tpr_service` etc.). The index contains url fields that come from the parent entity, so a suggested topics entity needs to be reindexed if the url alias for the parent entity changes.
