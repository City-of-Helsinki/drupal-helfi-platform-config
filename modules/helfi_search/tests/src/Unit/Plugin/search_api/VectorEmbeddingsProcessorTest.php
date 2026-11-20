<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_search\Unit\Plugin\search_api;

use Drupal\helfi_search\EmbeddingModel;
use Drupal\helfi_search\Plugin\search_api\processor\VectorEmbeddingsProcessor;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests property definitions exposed by the embeddings processor.
 */
#[Group('helfi_search')]
class VectorEmbeddingsProcessorTest extends UnitTestCase {

  /**
   * Each embedding model property includes its dimensions setting.
   */
  public function testPropertyDefinitionsIncludeDimensions(): void {
    $processor = new VectorEmbeddingsProcessor([], 'helfi_search_embeddings', []);
    $processor->setStringTranslation($this->getStringTranslationStub());

    $definitions = $processor->getPropertyDefinitions();

    foreach (EmbeddingModel::cases() as $model) {
      $this->assertArrayHasKey($model->fieldPrefix(), $definitions);
      $this->assertSame(
        $model->dimensions(),
        $definitions[$model->fieldPrefix()]->getSetting('embeddings_model_dimensions'),
      );
    }
  }

}
