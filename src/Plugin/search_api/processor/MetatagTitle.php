<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\MetatagManager;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the entity's customized metatag title to the indexed data.
 *
 * Editors can override the page title via the metatag fields. This processor
 * exposes that overridden title so the search result title can be replaced
 * with it instead of the plain entity label.
 *
 * The processor depends on the metatag module, but that dependency is optional:
 * it is removed from the list of available processors when metatag is not
 * installed.
 *
 * @see \Drupal\helfi_platform_config\EventSubscriber\SearchApiSubscriber::onGatheringProcessors()
 */
#[SearchApiProcessor(
  id: 'helfi_metatag_title',
  label: new TranslatableMarkup('Metatag title'),
  description: new TranslatableMarkup("Adds the entity's customized metatag title to the indexed data."),
  stages: [
    'add_properties' => 0,
  ],
)]
final class MetatagTitle extends ProcessorPluginBase {

  /**
   * Tokens to remove from the title template.
   */
  private const array STRIP_TOKENS = ['[site:page-title-suffix]'];

  /**
   * The metatag manager.
   */
  private MetatagManager $metatagManager;

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->metatagManager = $container->get('metatag.manager');
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $properties['helfi_search_title'] = new ProcessorProperty([
        'label' => $this->t('Metatag title'),
        'description' => $this->t("The entity's customized metatag title."),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param \Drupal\search_api\Item\ItemInterface<mixed> $item
   */
  public function addFieldValues(ItemInterface $item): void {
    $entity = $item->getOriginalObject()->getValue();

    if (!$entity instanceof EntityInterface) {
      return;
    }

    $title = NULL;

    // Metatag overrides are only available on content entities.
    // Defaults so the entity label unless an editor has customized
    // the title.
    if ($entity instanceof ContentEntityInterface) {
      $tags = $this->metatagManager->tagsFromEntity($entity);

      // Strip configured tokens from the template so
      // they are never resolved into the indexed title.
      if (!empty($tags['title'])) {
        $tags['title'] = $this->stripTokens($tags['title']);

        $value = $this->metatagManager->generateTokenValues($tags, $entity)['title'] ?? '';

        // Trim non-word character from string,
        // this removes leftover separators.
        $title = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $value) ?? NULL;
      }
    }

    // Fall back to the entity label when the title has not been customized.
    if (!is_string($title) || !$title) {
      $title = (string) $entity->label();
    }

    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(FALSE), NULL, 'helfi_search_title');

    foreach ($fields as $field) {
      $field->addValue($title);
    }
  }

  /**
   * Removes the configured tokens from a raw metatag value.
   */
  private function stripTokens(string $value): string {
    return trim(str_replace(self::STRIP_TOKENS, '', $value));
  }

}
