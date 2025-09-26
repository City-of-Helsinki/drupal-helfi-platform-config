<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_recommendations\Unit\Client;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\helfi_recommendations\Client\ApiClient;
use Drupal\helfi_recommendations\Client\ApiClientException;
use Drupal\helfi_recommendations\Client\Keyword;
use Drupal\helfi_recommendations\Entity\SuggestedTopics;
use Drupal\helfi_api_base\TextConverter\TextConverterInterface;
use Drupal\Tests\helfi_recommendations\Traits\AnnifApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
use Prophecy\Argument;

/**
 * Tests api client.
 *
 * @group helfi_recommendations
 */
class ApiClientTest extends UnitTestCase {

  use AnnifApiTestTrait;

  /**
   * Tests entity with invalid langcode.
   */
  public function testInvalidLanguage(): void {
    $entity = $this->mockEntity('xzz');
    $sut = $this->getSut([]);

    $this->assertNull($sut->suggest($entity));
    $this->assertEmpty($sut->suggestBatch([$entity]));
  }

  /**
   * Tests mixing languages with suggest endpoint.
   */
  public function testMixingLanguages(): void {
    $entities = [
      $this->mockEntity('en'),
      $this->mockEntity('sv'),
    ];

    $sut = $this->getSut([]);

    $this->expectException(\InvalidArgumentException::class);
    $sut->suggestBatch($entities);
  }

  /**
   * Tests entity that cannot be converted with text converter.
   */
  public function testUnknownEntity(): void {
    $entity = $this->mockEntity();
    $textConverter = $this->prophesize(TextConverterInterface::class);
    $textConverter
      ->applies(Argument::any())
      ->willReturn(FALSE);

    $sut = $this->getSut([], $textConverter->reveal());

    $this->assertNull($sut->suggest($entity));
    $this->assertEmpty($sut->suggestBatch([$entity]));
  }

  /**
   * Tests http error.
   */
  public function testHttpError(): void {
    $entity = $this->mockEntity();

    $sut = $this->getSut([
      new RequestException('Bad request', new Request('GET', 'test')),
    ]);

    $this->expectException(ApiClientException::class);
    $sut->suggest($entity);
  }

  /**
   * Tests http error.
   */
  public function testHttpErrorBatch(): void {
    $entity = $this->mockEntity();

    $sut = $this->getSut([
      new RequestException('Bad request', new Request('GET', 'test')),
    ]);

    $this->expectException(ApiClientException::class);
    $sut->suggestBatch([$entity]);
  }

  /**
   * Tests valid request.
   */
  public function testValidRequest(): void {
    $entity = $this->mockEntity();
    $sut = $this->getSut([
      new Response(body: $this->getFixture('suggest.json')),
    ]);

    $keywords = $sut->suggest($entity);

    $this->assertIsArray($keywords);
    $this->assertNotEmpty($keywords);

    foreach ($keywords as $keyword) {
      $this->assertInstanceOf(Keyword::class, $keyword);
    }

    // See test/fixtures/suggest.json.
    $this->assertEquals(reset($keywords)->label, "koneoppiminen");
  }

  /**
   * Tests valid batch request.
   */
  public function testValidBatchRequest(): void {
    $entities = [
      'foo' => $this->mockEntity(),
      'bar' => $this->mockEntity(),
    ];

    $httpClient = new Client([
      'handler' => function (Request $request) use ($entities) {
        $body = Utils::jsonDecode($request->getBody()->getContents(), TRUE);

        // Client uses document ids.
        $this->assertEquals(
          array_keys($entities),
          array_map(static fn ($doc) => $doc['document_id'], $body['documents'] ?? []),
        );

        $response = [];
        $fixture = Utils::jsonDecode($this->getFixture('suggest.json'), TRUE);
        foreach ($body['documents'] as $document) {
          $response[] = $fixture + [
            'document_id' => $document['document_id'],
          ];
        }

        return new Response(200, [], json_encode($response));
      },
    ]);

    $textConverterManager = $this->getTextConverterManager();
    $sut = new ApiClient($httpClient, $textConverterManager);

    $batch = $sut->suggestBatch($entities);

    $this->assertIsArray($batch);

    // Original keys should be preserved.
    $this->assertEquals(array_keys($entities), array_keys($batch));

    foreach ($batch as $keywords) {
      $this->assertIsArray($keywords);
      $this->assertNotEmpty($keywords);

      foreach ($keywords as $keyword) {
        $this->assertInstanceOf(Keyword::class, $keyword);
      }
    }
  }

  /**
   * Asserts that maximum batch size is not accepted.
   */
  public function testMaxBatchSize() : void {
    $sut = $this->getSut([]);

    $batch = array_fill(0, ApiClient::MAX_BATCH_SIZE + 1, $this->mockEntity());

    $this->expectException(\InvalidArgumentException::class);
    $sut->suggestBatch($batch);
  }

  /**
   * Gets service under test.
   */
  private function getSut(array $responses, ?TextConverterInterface $textConverter = NULL): ApiClient {
    $client = $this->createMockHttpClient($responses);
    $textConverterManager = $this->getTextConverterManager($textConverter);

    return new ApiClient($client, $textConverterManager);
  }

  /**
   * Mocks entity.
   *
   * @param string $langcode
   *   Entity langcode. The API supports 'fi','sv','en'.
   * @param bool|null $hasKeywords
   *   Value for keyword field ->isEmpty(), NULL for ->hasField() = FALSE.
   * @param bool|null $shouldSave
   *   Bool if $entity->save() should be called, NULL for no opinion.
   */
  private function mockEntity(string $langcode = 'fi', bool|NULL $hasKeywords = FALSE, bool|NULL $shouldSave = NULL): ContentEntityInterface {
    $language = $this->prophesize(LanguageInterface::class);
    $language
      ->getId()
      ->willReturn($langcode);

    $entity = $this->prophesize(ContentEntityInterface::class);
    $entity
      ->language()
      ->willReturn($language->reveal());

    $entity->getEntityTypeId()->willReturn('test_entity');
    $entity->bundle()->willReturn('test_entity');
    $entity->id()->willReturn($this->randomString());

    $topicsEntity = $this->prophesize(SuggestedTopics::class);

    $field = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $field->isEmpty()->willReturn(!$hasKeywords);
    $field->referencedEntities()->willReturn([$topicsEntity->reveal()]);

    $entity
      ->get(Argument::exact('field_topics'))
      ->willReturn($field->reveal());

    if (is_bool($shouldSave)) {
      if ($shouldSave) {
        $topicsEntity->set(Argument::any(), Argument::any())->shouldBeCalled();
        $topicsEntity->save()->shouldBeCalled();
      }
      else {
        $topicsEntity->save()->shouldNotBeCalled();
      }
    }

    return $entity->reveal();
  }

}
