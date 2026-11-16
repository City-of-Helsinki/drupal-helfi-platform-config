<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Plugin\search_api;

use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\Plugin\search_api\processor\Property\EmbeddingsProperty;
use Drupal\helfi_search\Plugin\search_api\processor\VectorEmbeddingsProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests property definitions exposed by the embeddings processor.
 */
#[Group('helfi_search')]
class VectorEmbeddingsProcessorTest extends UnitTestCase {

  /**
   * Each embedding model property includes its model setting.
   */
  public function testPropertyDefinitionsIncludeModel(): void {
    $processor = new VectorEmbeddingsProcessor([], 'helfi_search_embeddings', []);
    $processor->setStringTranslation($this->getStringTranslationStub());

    $definitions = $processor->getPropertyDefinitions();

    foreach (EmbeddingModel::cases() as $model) {
      $this->assertArrayHasKey($model->fieldPrefix(), $definitions);
      $this->assertInstanceOf(EmbeddingsProperty::class, $definitions[$model->fieldPrefix()]);
      $this->assertSame(
        $model,
        $definitions[$model->fieldPrefix()]->getSetting(EmbeddingsProperty::MODEL_SETTING),
      );
    }
  }

}
