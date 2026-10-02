<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_platform_config\EntityNotifications\EntityNotificationLazyBuilder;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders notifications for a content entity.
 */
#[ViewsField('helfi_entity_notifications')]
final class EntityNotifications extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): MarkupInterface|string {
    $entity = $this->getEntity($values);

    if (!$entity instanceof ContentEntityInterface) {
      return '';
    }

    $build = EntityNotificationLazyBuilder::lazyBuild($entity);

    return $this->getRenderer()->render($build);
  }

}
