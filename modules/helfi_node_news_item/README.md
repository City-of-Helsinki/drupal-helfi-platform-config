created: 
Drupal core field. Timestamp when the node was first created. Never changes after initial creation.

changed: 
Drupal core field. Timestamp of any modification to the entity, including programmatic updates, migrations, and API syncs. Updates automatically on every $node->save().

published_at: 
Provided by publication_date module. Timestamp when the node was first published. Useful for displaying "Published on" date to users.

changed_at: 
Custom field for news_item. Timestamp of the last manual form save by an editor. Unlike changed, it only updates when content is edited through the UI, not during migrations or programmatic updates. Used for displaying "Modified on" date to users.
