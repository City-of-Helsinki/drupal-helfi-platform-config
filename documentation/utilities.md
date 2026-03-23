# Utilities and helpers

## Drush-command to manage "Clear-Site-Data"-header

### What and why?

https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Clear-Site-Data
> The HTTP Clear-Site-Data response header sends a signal to the client that it should remove all browsing data of certain types (cookies, storage, cache) associated with the requesting website. It allows web developers to have more control over the data stored by browsers for their origins

### `drush helfi:clear-site-data`

Tool to manage the "Clear-Site-Data"-header.

```
Examples:
drush helfi:clear-site-data
drush helfi:clear-site-data enable cache,storage --ttl=24
drush helfi:clear-site-data enable "*"
drush helfi:clear-site-data disable

Arguments:
 [operation]  The operation to perform. [default: status]
 [directives] A comma separated list of directives to enable.

Options:
--ttl=TTL The expiration time in hours. [default: 1]
```

* Valid directive values are:
  * `cache`
  * `clientHints`
  * `cookies`
  * `executionContexts`
  * `prefetchCache`
  * `prerenderCache`
  * `storage`
  * `*` (aka. "all")
* When `*` is included, all other directives are ignored.
* Setting the `*`-directive is best done with `drush helfi:clear-site-data enable "*"` to avoid bash evaluating bare `*` into something else.
* TTL can be set between 1 and 24 hours, and defaults to 1 hour.
* The tool comes with a cron-hook to disable expired headers automatically.
 