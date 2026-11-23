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

Some indexes are written to by several instances. They are listed with their main instance in `Drupal\helfi_platform_config\SearchAPI\RestrictedSearchIndex`.

Outside the main instance, `Drupal\helfi_platform_config\SearchAPI\RestrictedElasticSearchBackend` leaves a shared Elasticsearch index unchanged: creating, deleting, updating and clearing it are skipped. Documents are still indexed and deleted as usual. The class replaces the `elasticsearch` backend plugin through `hook_search_api_backend_info_alter()`.

### Clearing a shared index

`drush search-api:clear` (`sapi-c`) on a shared index is guarded by `Drupal\helfi_platform_config\Drush\Listeners\SearchApiClearListener`:

* On the main instance, the clear has to be confirmed (or run with `--yes`).
* On every other instance, the clear is blocked.

### Reindexing on other instances

A clear deletes the documents of every instance, but only the clearing instance's tracker knows its items must be reindexed. So after a successful `sapi-c` on a shared index, the clearing instance sends an [Azure PubSub message](https://github.com/City-of-Helsinki/drupal-module-helfi-api-base/blob/main/documentation/pubsub-messaging.md):

```json
{"search_api_reindex": {"index": "embeddings", "instance": "etusivu"}}
```

`Drupal\helfi_platform_config\EventSubscriber\SearchApiReindexSubscriber` receives it on the other instances. If the index exists and is enabled there, it marks all items for reindexing (same as `drush search-api:reset-tracker`). The items are then indexed on the next indexing run. The sending instance ignores its own message.

Good to know:

* Receiving requires the `drush helfi:azure:pubsub-listen` worker and the `pubsub` vault item. If sending fails, a warning is logged and the clear still succeeds; the other instances then have to run `drush sapi-r <index>` manually.
* Only `drush sapi-c` sends the message. Clearing from the Search API admin UI does not.

## Path alias updates

Quite a few indexes contain fields with url aliases, and usually this data stays up-to-date via regular content updates that trigger reindexing. However additional update hooks are needed to cover path alias operations that happen outside the content entity update context, like:

* Drupal bulk generate tool in `/admin/config/search/path/update_bulk`
* Update hook batch updates (like in `helfi_platform_config_update_11009()`)

See for example:

* Generic hooks that cover regular routed content entities: `Drupal\helfi_platform_config\Hook\PathAliasHooks`
* Special handling for suggested topics: `Drupal\helfi_recommendations\Hook\PathAliasHooks`
  * `suggestions`-index contains `suggested_topics`-entities, which are entity reference field targets from content entities (`node`, `tpr_service` etc.). The index contains url fields that come from the parent entity, so a suggested topics entity needs to be reindexed if the url alias for the parent entity changes.
