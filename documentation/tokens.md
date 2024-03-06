# Tokens

Helfi platform config implements `hook_tokens()`, and with the help of `helfi_platform_config.og_image_manager`, the `[*:shareable-image]` token is provided. Modules may implement services that handle this token for their entity types.

Modules should still implement `hook_tokens_info` to provide information about the implemented token.

## Defining image builder service

Add a new service:

```yml
# yourmodule/yourmodule.services.yml
  yourmodule.og_image.your_entity_type:
    class: Drupal\yourmodule\Token\YourEntityImageBuilder
    arguments: []
    tags:
      - { name: helfi_platform_config.og_image_builder, priority: 100 }
```

```php
# yourmodule/src/Token/YourEntityImageBuilder.php
<?php

declare(strict_types=1);

namespace Drupal\yourmodule\Token;

use Drupal\helfi_platform_config\Token\OGImageBuilderInterface;

/**
 * Handles token hooks.
 */
final class YourEntityImageBuilder implements OGImageBuilderInterface {

  /**
   * {@inheritDoc}
   */
  public function applies(EntityInterface $entity): bool {
    return $entity instanceof YourEntity;
  }

  /**
   * {@inheritDoc}
   */
  public function buildUrl(EntityInterface $entity): ?string {
    assert($entity instanceof YourEntity);
    return $entity->field_image->entity->toUrl();
  }

}
```
