<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_paragraphs_news_list\Kernel\KernelTestBase;
use Drupal\external_entities\ExternalEntityStorage;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * A base class for storage client tests.
 */
abstract class StorageClientTestBase extends KernelTestBase {

  use ApiTestTrait;

  /**
   * Gets the storage name.
   *
   * @return string
   *   The storage name.
   */
  abstract protected function getStorageName() : string;

  /**
   * Asserts that the expected requests were sent.
   *
   * @param array $expected
   *   The expected container request.
   * @param array $container
   *   The history container.
   */
  protected function assertHttpHistoryContainer(array $expected, array $container): void {
    $this->assertCount(1, $container);
    $this->assertInstanceOf(Request::class, $container[0]['request']);
    /** @var \GuzzleHttp\Psr7\Request $request */
    $request = $container[0]['request'];

    $body = json_decode($request->getBody()->getContents(), TRUE);
    $this->assertEquals($expected, $body);
  }

  /**
   * Gets the SUT.
   *
   * @param array $container
   *   The transaction container.
   * @param \Psr\Http\Message\ResponseInterface[]|\GuzzleHttp\Exception\GuzzleException[] $responses
   *   The responses.
   *
   * @return \Drupal\external_entities\ExternalEntityStorage
   *   The storage.
   */
  public function getSut(array &$container, array $responses) : ExternalEntityStorage {
    $mock = $this->createMockHistoryMiddlewareHttpClient($container, $responses);
    $client = ClientBuilder::create()
      ->setHttpClient($mock)
      ->build();

    $this->container->set('helfi_platform_config.etusivu_elastic_client', $client);
    $storage = $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage($this->getStorageName());
    $this->assertInstanceOf(ExternalEntityStorage::class, $storage);
    return $storage;
  }

  /**
   * Make sure elastic query exceptions are caught.
   */
  public function testRequestException() : void {
    $container = [];
    $sut = $this->getSut($container, [
      new Response(500),
      new Response(500),
    ]);
    $this->assertEmpty($sut->loadMultiple([123]));
    $this->assertEmpty($sut->getQuery()->accessCheck(FALSE)->execute());
  }

  /**
   * Make sure trying to save and delete entity does nothing.
   */
  public function testSaveAndDelete() : void {
    $container = [];
    $storage = $this->getSut($container, [
      $this->createElasticsearchResponse([]),
    ]);
    $entity = $storage->create(['type' => 'helfi_news']);
    $entity->save();
    $storage->save($entity);
    $storage->delete([$entity]);
  }

}
