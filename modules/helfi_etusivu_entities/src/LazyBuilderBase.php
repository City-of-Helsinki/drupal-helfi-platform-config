<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Base class for lazy builder.
 */
class LazyBuilderBase implements TrustedCallbackInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
    protected LanguageManagerInterface $languageManager,
    protected DefaultLanguageResolver $defaultLanguageResolver,
    protected PagePathMatcher $pagePathMatcher,
    #[Autowire(service: 'logger.channel.helfi_etusivu_entities')]
    protected LoggerInterface $logger,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Get global entity storage.
   *
   * @param string $entityTypeId
   *   External entity type.
   */
  protected function getExternalEntityStorage(string $entityTypeId): ExternalEntityStorageInterface {
    $globalEntityStorage = $this->entityTypeManager->getStorage($entityTypeId);
    if ($globalEntityStorage instanceof ExternalEntityStorageInterface) {
      return $globalEntityStorage;
    }

    throw new \InvalidArgumentException("$entityTypeId is not external entity type");
  }

  /**
   * Get current page's entity from given possibilities.
   *
   * @param array $entityTypes
   *   Entity names to be used to check the current page.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Current page's entity, if any.
   */
  protected function getCurrentPageEntity(array $entityTypes): ?EntityInterface {
    foreach ($entityTypes as $entityType) {
      $pageEntity = $this->routeMatch->getParameter($entityType);
      if (!empty($pageEntity) && $pageEntity instanceof EntityInterface) {
        return $pageEntity;
      }
    }
    return NULL;
  }

  /**
   * Checks if entity has reference to another entity.
   *
   * @param string $fieldName
   *   Entity reference field name.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\EntityInterface|null $target
   *   Target entity.
   *
   * @return bool
   *   True if $entity has reference to $target.
   */
  protected function hasReference(string $fieldName, FieldableEntityInterface $entity, ?EntityInterface $target): bool {
    // Get announcement's referenced entities from the appropriate field,
    // depending on the current page's entity.
    $referencedEntities = [];

    if ($entity->hasField($fieldName)) {
      $field = $entity->get($fieldName);
      assert($field instanceof EntityReferenceFieldItemListInterface);
      $referencedEntities = $field->referencedEntities();
    }

    if ($target) {
      foreach ($referencedEntities as $referencedEntity) {
        if ($referencedEntity->id() === $target->id()) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Checks whether the current path matches a page path pattern field.
   *
   * Patterns use the same syntax as the core request path condition: one path
   * per line, "*" as a wildcard, and "<front>" for the front page.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The announcement or survey.
   * @param string $fieldName
   *   The string field that stores the patterns.
   *
   * @return bool
   *   TRUE when the current alias or internal path matches.
   */
  protected function matchesPagePaths(FieldableEntityInterface $entity, string $fieldName): bool {
    return $this->pagePathMatcher->matches($entity, $fieldName);
  }

  /**
   * Get the content language codes.
   *
   * @return array
   *   Content language codes.
   */
  protected function getContentLangcodes(): array {
    // Also fetch english announcements for languages with non-standard support.
    $langcodes[] = $this->defaultLanguageResolver->getCurrentOrFallbackLanguage(LanguageInterface::TYPE_CONTENT);
    $currentLangcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    if (reset($langcodes) !== $currentLangcode) {
      $langcodes[] = $currentLangcode;
    }

    return $langcodes;
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks(): array {
    return ['lazyBuild'];
  }

}
