<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Decides whether an announcement or survey is shown on the current page.
 */
final class PageVisibility {

  /**
   * Shown on all pages.
   */
  public const SPECIFICITY_ALL = 0;

  /**
   * Shown on unit or service pages.
   */
  public const SPECIFICITY_REGION = 1;

  /**
   * Shown on single pages or paths.
   */
  public const SPECIFICITY_PAGE = 2;

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly PagePathMatcher $pagePathMatcher,
  ) {}

  /**
   * Checks whether the entity has page rules.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The announcement or survey.
   * @param \Drupal\helfi_etusivu_entities\VisibilityFields $fields
   *   The visibility fields of the bundle.
   *
   * @return bool
   *   TRUE when a page reference or path field has a value.
   */
  private function isRestricted(FieldableEntityInterface $entity, VisibilityFields $fields): bool {
    return $this->hasAnyValue($entity, [...array_values($fields->references()), $fields->paths]);
  }

  /**
   * Checks whether the entity is shown on the current page.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The announcement or survey.
   * @param \Drupal\helfi_etusivu_entities\VisibilityFields $fields
   *   The visibility fields of the bundle.
   *
   * @return bool
   *   TRUE when the entity is shown on all pages, references the entity of the
   *   current page, or has a path pattern that matches the current path.
   */
  public function isVisible(FieldableEntityInterface $entity, VisibilityFields $fields): bool {
    if ($fields->allPages === NULL) {
      if (!$this->isRestricted($entity, $fields)) {
        return TRUE;
      }
    }
    elseif ($this->isOnAllPages($entity, $fields)) {
      return TRUE;
    }

    return $this->referencesCurrentPage($entity, $fields)
      || $this->pagePathMatcher->matches($entity, $fields->paths);
  }

  /**
   * Gets how specific the page rules of the entity are.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The announcement or survey.
   * @param \Drupal\helfi_etusivu_entities\VisibilityFields $fields
   *   The visibility fields of the bundle.
   *
   * @return int
   *   One of the SPECIFICITY_* constants.
   */
  public function specificity(FieldableEntityInterface $entity, VisibilityFields $fields): int {
    if ($this->isOnAllPages($entity, $fields)) {
      return self::SPECIFICITY_ALL;
    }
    if ($this->hasAnyValue($entity, array_values($fields->regionReferences))) {
      return self::SPECIFICITY_REGION;
    }
    if ($this->hasAnyValue($entity, [...array_values($fields->pageReferences), $fields->paths])) {
      return self::SPECIFICITY_PAGE;
    }
    return self::SPECIFICITY_ALL;
  }

  /**
   * Checks whether the entity references the entity of the current page.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The announcement or survey.
   * @param \Drupal\helfi_etusivu_entities\VisibilityFields $fields
   *   The visibility fields of the bundle.
   *
   * @return bool
   *   TRUE when the reference field for the current page's entity type
   *   contains the current page's entity.
   */
  private function referencesCurrentPage(FieldableEntityInterface $entity, VisibilityFields $fields): bool {
    $page = $this->getCurrentPageEntity(array_keys($fields->references()));
    if (!$page) {
      return FALSE;
    }

    $fieldName = $fields->references()[$page->getEntityTypeId()];
    if (!$entity->hasField($fieldName)) {
      return FALSE;
    }

    $ids = array_map('strval', array_column($entity->get($fieldName)->getValue(), 'target_id'));
    return in_array((string) $page->id(), $ids, TRUE);
  }

  /**
   * Gets the entity of the current page.
   *
   * @param string[] $entityTypes
   *   The entity types to look for in the route parameters.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The first entity found, if any.
   */
  private function getCurrentPageEntity(array $entityTypes): ?EntityInterface {
    foreach ($entityTypes as $entityType) {
      $entity = $this->routeMatch->getParameter($entityType);
      if ($entity instanceof EntityInterface) {
        return $entity;
      }
    }
    return NULL;
  }

  /**
   * Checks whether the all pages field of the entity is set.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The announcement or survey.
   * @param \Drupal\helfi_etusivu_entities\VisibilityFields $fields
   *   The visibility fields of the bundle.
   *
   * @return bool
   *   TRUE when the entity is shown on all pages.
   */
  private function isOnAllPages(FieldableEntityInterface $entity, VisibilityFields $fields): bool {
    return $fields->allPages !== NULL
      && $entity->hasField($fields->allPages)
      && (bool) $entity->get($fields->allPages)->value;
  }

  /**
   * Checks whether any of the given fields has a value.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param string[] $fieldNames
   *   The field names.
   *
   * @return bool
   *   TRUE when at least one field exists and is not empty.
   */
  private function hasAnyValue(FieldableEntityInterface $entity, array $fieldNames): bool {
    foreach ($fieldNames as $fieldName) {
      if ($entity->hasField($fieldName) && !$entity->get($fieldName)->isEmpty()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
