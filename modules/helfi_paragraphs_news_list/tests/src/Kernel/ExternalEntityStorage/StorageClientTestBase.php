<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel\ExternalEntityStorage;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\helfi_paragraphs_news_list\Kernel\KernelTestBase;
use Drupal\external_entities\ExternalEntityStorage;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Prophecy\Argument;

/**
 * A base class for storage client tests.
 */
abstract class StorageClientTestBase extends KernelTestBase {

  /**
   * Gets the storage name.
   *
   * @return string
   *   The storage name.
   */
  abstract protected function getStorageName() : string;

  /**
   * Gets the SUT.
   *
   * @param \Elastic\Elasticsearch\Client $client
   *   The client mock.
   *
   * @return \Drupal\external_entities\ExternalEntityStorage
   *   The storage.
   */
  public function getSut(Client $client) : ExternalEntityStorage {
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
    $client = $this->prophesize(Client::class);
    $client->search(Argument::any())
      ->willThrow(new ClientResponseException('Message'));
    $sut = $this->getSut($client->reveal());
    $this->assertEmpty($sut->loadMultiple([123]));
    $this->assertEmpty($sut->getQuery()->accessCheck(FALSE)->execute());
  }

  /**
   * Make sure trying to save and delete entity does nothing.
   */
  public function testSaveAndDelete() : void {
    $client = $this->prophesize(Client::class);
    $storage = $this->getSut($client->reveal());
    $entity = $storage->create(['type' => 'helfi_news']);
    $entity->save();
    $storage->save($entity);
    $storage->delete([$entity]);
  }

}
