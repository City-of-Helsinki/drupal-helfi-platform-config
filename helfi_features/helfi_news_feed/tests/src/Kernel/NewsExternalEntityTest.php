<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_news_feed\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\external_entities\Entity\ExternalEntity;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\TestLoggerTrait;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;

/**
 * Tests News external entity.
 *
 * @group helfi_news_feed
 */
class NewsExternalEntityTest extends KernelTestBase {

  use ApiTestTrait;
  use TestLoggerTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setUpMockLogger();
  }

  /**
   * Gets the mocked external entity storage.
   *
   * @param array $responses
   *   The mock response.
   *
   * @return \Drupal\external_entities\ExternalEntityStorageInterface
   *   The storage.
   */
  private function getExternalEntityStorage(array $responses = []) : ExternalEntityStorageInterface {
    $client = $this->createMockHttpClient($responses);
    $this->container->set('http_client', $client);
    return $this->container->get('entity_type.manager')
      ->getStorage('helfi_news');
  }

  /**
   * Make sure trying to delete entity throws an exception.
   */
  public function testDelete() : void {
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('::delete() is not supported.');
    $this->getExternalEntityStorage()->delete([ExternalEntity::create(['type' => 'helfi_news'])]);
  }

  /**
   * Tests query() and loadMultiple() exception.
   *
   * Make request exceptions are caught and logged.
   */
  public function testRequestExceptions() : void {
    $this->expectLogMessage('Not found', RequestException::class);
    $storage = $this->getExternalEntityStorage([
      new RequestException('Not found', $this->createMock(RequestInterface::class)),
    ]);
    $entity = $storage->load(1);
    $this->assertEmpty($entity);
  }

}
