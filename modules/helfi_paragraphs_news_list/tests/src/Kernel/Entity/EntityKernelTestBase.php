<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\Entity;

use Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\Term;
use Drupal\Tests\helfi_paragraphs_news_list\Kernel\KernelTestBase;
use Drupal\external_entities\ExternalEntityStorage;

/**
 * Base class for news feed entity tests.
 */
abstract class EntityKernelTestBase extends KernelTestBase {

  /**
   * The neighbourhood term.
   *
   * @var \Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\Term|null
   */
  protected ?Term $neighbourhood = NULL;

  /**
   * The tag term.
   *
   * @var \Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\Term|null
   */
  protected ?Term $tag = NULL;

  /**
   * The group term.
   *
   * @var \Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\Term|null
   */
  protected ?Term $group = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->neighbourhood = $this->getStorage('helfi_news_neighbourhoods')->create([
      'id' => 'ff10dbf0-6b00-400b-a8a9-4fae102ea92c:fi',
      'title' => 'Neighbourhood',
    ]);
    $this->tag = $this->getStorage('helfi_news_tags')->create([
      'id' => 'ca9a7c2e-acbb-4f03-938b-9cd86fd606ac:fi',
      'title' => 'Tags',
    ]);
    $this->group = $this->getStorage('helfi_news_groups')->create([
      'id' => 'e30fa7be-4d13-4216-8658-103fb9a26c8c:fi',
      'title' => 'Tags',
    ]);

  }

  /**
   * Gets the storage for given entity type.
   *
   * @param string $entityType
   *   The entity type.
   *
   * @return \Drupal\external_entities\ExternalEntityStorage
   *   The storage.
   */
  protected function getStorage(string $entityType) : ExternalEntityStorage {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($entityType);
    assert($storage instanceof ExternalEntityStorage);

    return $storage;
  }

}
