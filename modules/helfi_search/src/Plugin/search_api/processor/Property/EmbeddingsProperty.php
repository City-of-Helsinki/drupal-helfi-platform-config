<?php

declare(strict_types=1);

namespace Drupal\helfi_search\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\helfi_search\EmbeddingModel;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;

/**
 * Defines an embedding property.
 *
 * Maps field configuration to Elasticsearch index settings.
 *
 * @see \Drupal\helfi_search\EventSubscriber\SearchApiSubscriber::mapElasticFields()
 */
class EmbeddingsProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * The data definition setting that holds the embedding model.
   */
  public const string MODEL_SETTING = 'embeddings_model';

  /**
   * {@inheritdoc}
   *
   * @return array{dimensions: int|null}
   *   The default configuration.
   */
  public function defaultConfiguration(): array {
    return [
      'dimensions' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param \Drupal\search_api\Item\FieldInterface<mixed> $field
   * @phpstan-param array<string, mixed> $form
   * @phpstan-return array<string, mixed>
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state): array {
    $configuration = $field->getConfiguration();
    $model = $this->getSetting(self::MODEL_SETTING);
    $max = $model instanceof EmbeddingModel ? $model->dimensions() : NULL;

    $form['dimensions'] = [
      '#type' => 'number',
      '#title' => $this->t('Dimensions'),
      '#description' => $this->t('Shorten the indexed vectors to this many dimensions to save storage. Leave empty to use the full model dimensions (@max).', ['@max' => $max ?? '-']),
      '#default_value' => $configuration['dimensions'] ?? NULL,
      '#min' => 1,
      '#max' => $max,
      '#step' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param \Drupal\search_api\Item\FieldInterface<mixed> $field
   * @phpstan-param array<string, mixed> $form
   */
  public function submitConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state): void {
    $dimensions = $form_state->getValue('dimensions');
    $field->setConfiguration([
      'dimensions' => $dimensions === NULL || $dimensions === '' ? NULL : (int) $dimensions,
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param \Drupal\search_api\Item\FieldInterface<mixed> $field
   */
  public function getFieldDescription(FieldInterface $field): string {
    return (string) $this->t('Dimensions: @dimensions', ['@dimensions' => self::getDimensions($field) ?? '-']);
  }

  /**
   * Gets the vector dimensions of an embeddings field.
   *
   * @param \Drupal\search_api\Item\FieldInterface<mixed> $field
   *   The index field.
   *
   * @return int|null
   *   The configured dimensions. Full model dimensions if the field is not
   *   configured, or NULL if neither is known.
   */
  public static function getDimensions(FieldInterface $field): ?int {
    $model = $field->getDataDefinition()->getSetting(self::MODEL_SETTING);
    $max = $model instanceof EmbeddingModel ? $model->dimensions() : NULL;
    $dimensions = (int) ($field->getConfiguration()['dimensions'] ?? 0);

    if ($dimensions <= 0) {
      return $max;
    }

    return $max !== NULL ? min($dimensions, $max) : $dimensions;
  }

}
