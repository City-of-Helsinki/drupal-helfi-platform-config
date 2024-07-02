<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for etusivu remote entities blocks.
 */
abstract class EtusivuEntityBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new AnnouncementsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\helfi_api_base\Language\DefaultLanguageResolver $defaultLanguageResolver
   *   Default language resolver.
   */
  final public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly LoggerInterface $logger,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly DefaultLanguageResolver $defaultLanguageResolver,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('logger.channel.helfi_etusivu_entities'),
      $container->get(RouteMatchInterface::class),
      $container->get(EntityTypeManagerInterface::class),
      $container->get(EntityFieldManagerInterface::class),
      $container->get(LanguageManagerInterface::class),
      $container->get(DefaultLanguageResolver::class),
    );
  }

  /**
   * Get global entity storage.
   *
   * @param string $entityTypeId
   *   External entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getExternalEntityStorage(string $entityTypeId): ExternalEntityStorageInterface {
    $globalEntityStorage = $this->entityTypeManager->getStorage($entityTypeId);
    if ($globalEntityStorage instanceof ExternalEntityStorageInterface) {
      return $globalEntityStorage;
    }

    throw new \InvalidArgumentException("$entityTypeId is not external entity type");
  }

  /**
   * Gets content langcodes.
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

}
