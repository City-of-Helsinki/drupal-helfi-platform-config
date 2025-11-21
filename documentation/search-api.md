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