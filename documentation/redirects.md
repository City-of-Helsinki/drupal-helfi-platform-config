# Publishable redirects

This module alters the [redirect entity type](../src/Entity/PublishableRedirect.php)
from the [`redirect` module](https://www.drupal.org/project/redirect)
so that it implements `EntityPublishedInterface` and has an `is_custom` field.

The redirect entities added or updated by any user from through the
entity from are automatically permanently marked as custom, while
redirects created automatically are not custom.

## Redirect cleaner

If enabled, a cleanup process handles **non-custom** redirect entities
that are older than a configured age. The cleanup behavior is configurable:

- **Expiration age** is defined using a `strtotime()`-compatible relative
  time string. F.e. `-6 months` or `-1 year`.
- **Action** can be either:
  - `unpublish`, which disables the redirect by unpublishing it
  - `delete`, which deletes the redirect entity permanently

By default, expired non-custom redirects are **unpublished after six months**.

### Enabling the feature

```php
\Drupal::configFactory()
  ->getEditable('helfi_platform_config.redirect_cleaner')
  ->set('enable', TRUE)
  ->set('expire_after', '-6 months')
  ->set('action', 'delete')
  ->save();
```
