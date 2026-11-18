<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Kernel\Translation;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\helfi_platform_config\Kernel\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Base class for the outdated translation tests.
 *
 * The 'page' bundle has the 'changed_at' field (opted in by
 * helfi_platform_config_update_test), the 'article' bundle does not.
 */
abstract class OutdatedTranslationTestBase extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The 'changed_at' time of the original translation in the tests.
   */
  protected const int ORIGINAL = 1_700_000_000;

  /**
   * One day in seconds.
   */
  protected const int DAY = 86400;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'language',
    'content_translation',
    'helfi_platform_config_update_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['system', 'node', 'filter', 'language']);

    ConfigurableLanguage::createFromLangcode('fi')->save();
    ConfigurableLanguage::createFromLangcode('sv')->save();

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $manager = $this->container->get('content_translation.manager');
    $manager->setEnabled('node', 'page', TRUE);
    $manager->setEnabled('node', 'article', TRUE);
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    // Install the content translation metadata fields added by enabling
    // translation.
    $updateManager = $this->container->get('entity.definition_update_manager');
    foreach ($manager->getTranslationHandler('node')->getFieldDefinitions() as $name => $definition) {
      assert($definition instanceof FieldStorageDefinitionInterface);
      $updateManager->installFieldStorageDefinition($name, 'node', 'content_translation', $definition);
    }
  }

  /**
   * Creates a Finnish node with translations.
   *
   * @param int|null $original
   *   The 'changed_at' time of the original, or NULL for no value.
   * @param array<string, int|null> $translations
   *   The 'changed_at' time of each translation, or NULL for no value, keyed
   *   by langcode.
   * @param string $type
   *   The bundle.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  protected function createTranslatedNode(?int $original, array $translations, string $type = 'page'): NodeInterface {
    $node = Node::create(['type' => $type, 'title' => 'fi', 'langcode' => 'fi']);
    $this->setChangedAt($node, $original);

    foreach ($translations as $langcode => $changed) {
      $this->setChangedAt($node->addTranslation($langcode, ['title' => $langcode]), $changed);
    }

    $node->save();

    return $node;
  }

  /**
   * Sets the 'changed_at' time.
   *
   * @param \Drupal\node\NodeInterface $translation
   *   The translation.
   * @param int|null $changed
   *   The time, or NULL for no value.
   */
  private function setChangedAt(NodeInterface $translation, ?int $changed): void {
    if ($changed !== NULL) {
      $translation->set('changed_at', $changed);
    }
  }

}
