<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_platform_config\EntityNotifications\EntityNotificationLazyBuilder;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Entity\Render\EntityTranslationRenderTrait;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Renders notifications for a content entity.
 */
#[ViewsField('helfi_entity_notifications')]
final class EntityNotifications extends FieldPluginBase {

  use EntityTranslationRenderTrait;

  /**
   * Constructs a new instance.
   *
   * @phpstan-param array<string, mixed> $configuration
   * @phpstan-param string $plugin_id
   * @phpstan-param array<string, mixed> $plugin_definition
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
    protected readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // Select the langcode of the row so the translation can be resolved.
    if ($this->languageManager->isMultilingual()) {
      $this->getEntityTranslationRenderer()->query($this->query, $this->relationship);
    }
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

    $entity = $this->getEntityTranslationByRelationship($entity, $values, $this->relationship ?: 'none');
    assert($entity instanceof ContentEntityInterface);

    $build = EntityNotificationLazyBuilder::lazyBuild($entity);

    return $this->getRenderer()->render($build);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return $this->getEntityType();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityRepository(): EntityRepositoryInterface {
    return $this->entityRepository;
  }

  /**
   * {@inheritdoc}
   */
  protected function getLanguageManager(): LanguageManagerInterface {
    return $this->languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getView(): ViewExecutable {
    return $this->view;
  }

}
