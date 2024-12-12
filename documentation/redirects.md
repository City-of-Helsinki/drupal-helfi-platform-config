# Publishable redirects

This module alters [redirect entity type](../src/Entity/PublishableRedirect.php) from
[`redirect` module](https://www.drupal.org/project/redirect)
so that it implements EntityPublishedInterface and has
`is_custom` field.

The redirect entities added or updated by any user from through the
entity from are automatically permanently marked as custom, while
redirects created automatically are not custom.

If enabled, a cron job unpublished non-custom redirect entities that
are more than 6 months old. Enable the feature with:

```php
\Drupal::configFactory()
  ->getEditable('helfi_platform_config.redirect_cleaner')
  ->set('enable', TRUE)
  ->save();
```

