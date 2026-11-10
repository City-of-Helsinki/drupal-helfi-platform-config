<?php

declare(strict_types=1);

namespace Drupal\helfi_etusivu_entities;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Drupal\node\NodeInterface;

/**
 * Loads the published local announcements or surveys of the current language.
 */
final class LocalNodeLoader {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly DefaultLanguageResolver $defaultLanguageResolver,
  ) {}

  /**
   * Loads the published nodes that are not published to other instances.
   *
   * Nodes published to other instances are shown through the remote
   * entities instead.
   *
   * @param string $bundle
   *   The node bundle.
   * @param string|null $newestFirstField
   *   A field to sort by, newest first. NULL keeps the default order.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The nodes.
   */
  public function load(string $bundle, ?string $newestFirstField = NULL): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('langcode', $this->getContentLangcodes(), 'IN');

    if ($newestFirstField) {
      $query->sort($newestFirstField, 'DESC');
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
    if (isset($fields['field_publish_externally'])) {
      $query->condition('field_publish_externally', FALSE);
    }

    $nodes = $storage->loadMultiple($query->execute());
    return array_values(array_filter($nodes, static fn ($node) => $node instanceof NodeInterface));
  }

  /**
   * Gets the content language codes.
   *
   * English content is loaded too for languages with non-standard support.
   *
   * @return string[]
   *   The language codes.
   */
  private function getContentLangcodes(): array {
    $langcodes = [$this->defaultLanguageResolver->getCurrentOrFallbackLanguage(LanguageInterface::TYPE_CONTENT)];
    $current = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    if ($langcodes[0] !== $current) {
      $langcodes[] = $current;
    }
    return $langcodes;
  }

}
