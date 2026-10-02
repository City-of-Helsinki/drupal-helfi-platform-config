<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\helfi_platform_config\EntityNotifications\EntityNotificationManager;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders notifications for a content entity.
 */
#[ViewsField('helfi_entity_notifications')]
final class EntityNotifications extends FieldPluginBase {

  /**
   * Constructs a EntityNotifications object.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\helfi_platform_config\EntityNotifications\EntityNotificationManager $notificationManager
   *   The entity notification manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly EntityNotificationManager $notificationManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

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

    $cacheability = new CacheableMetadata();
    $notifications = $this->notificationManager->getNotifications($entity, $cacheability);

    $build = [];
    if ($notifications) {
      $build = [
        '#theme' => 'helfi_entity_notifications',
        '#notifications' => $notifications,
        '#attached' => ['library' => ['helfi_platform_config/entity_notifications']],
      ];
    }

    $cacheability->applyTo($build);

    return $this->getRenderer()->render($build);
  }

}
