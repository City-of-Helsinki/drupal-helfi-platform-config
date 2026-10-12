# Entity notifications

`EntityNotificationManager` provides notes to users about content entities.

The notifications are implemented by services tagged with
`helfi_platform_config.entity_notification_provider`:

```yaml
services:
  Drupal\my_module\EntityNotifications\MyNotificationProvider:
    tags:
      - { name: helfi_platform_config.entity_notification_provider }
```

The provider returns a list of `EntityNotification` objects for an entity.

The `helfi_entity_notifications` Views field uses the notification system
for drawing notifications in Drupal views. The notification URL is rendered
in view fields.
