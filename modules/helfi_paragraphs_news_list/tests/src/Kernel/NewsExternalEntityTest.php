<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_api_base\Traits\TestLoggerTrait;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;

/**
 * Tests News external entity.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsExternalEntityTest extends KernelTestBase {

  use ApiTestTrait;
  use TestLoggerTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setUpMockLogger();
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);
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
    $externalEntityStorage = $this->getExternalEntityStorage();
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('::delete() is not supported.');
    $externalEntityStorage->delete([$externalEntityStorage->create(['type' => 'helfi_news'])]);
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
