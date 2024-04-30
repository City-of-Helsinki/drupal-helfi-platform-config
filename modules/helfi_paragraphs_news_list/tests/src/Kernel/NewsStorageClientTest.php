<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_news_list\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\external_entities\ExternalEntityStorage;
use Drupal\helfi_paragraphs_news_list\Entity\ExternalEntity\News;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Prophecy\Argument;

/**
 * Tests news storage client.
 *
 * @group helfi_paragraphs_news_list
 */
class NewsStorageClientTest extends KernelTestBase {

  public function getSut(Client $client) : ExternalEntityStorage {
    $this->container->set('helfi_paragraphs_news_list.elastic_client', $client);
    return $this->container->get(EntityTypeManagerInterface::class)
      ->getStorage('helfi_news');
  }

  /**
   * Make sure elastic query exceptions are caught.
   */
  public function testRequestException() : void {
    $client = $this->prophesize(Client::class);
    $client->search(Argument::any())
      ->willThrow(new BadRequest400Exception('Message'));
    $sut = $this->getSut($client->reveal());
    $this->assertEmpty($sut->loadMultiple([123]));
    $this->assertEmpty($sut->getQuery()->execute());
  }

  /**
   * Tests load multiple.
   */
  public function testLoadMultiple() : void {
    $client = $this->prophesize(Client::class);
    $client->search(Argument::any())
      ->shouldBeCalled()
      ->willReturn([], [
        'hits' => [
          'hits' => [
            [
              '_source' => [
                'uuid_langcode' => ['123'],
                'uuid' => ['uuid-123'],
                'title' => ['test title'],
                'field_news_groups' => ['Test groups'],
                'field_news_item_tags' => ['Test tag'],
                'field_news_neighbourhoods' => ['Test neighbourhood'],
                'url' => ['https://localhost'],
                'published_at' => [1234567],
                'short_title' => ['test shorttitle'],
              ],
            ],
          ],
        ],
      ]);
    $client->search(Argument::any())
      ->shouldBeCalled();
    $sut = $this->getSut($client->reveal());
    $this->assertEmpty($sut->loadMultiple([123]));

    $values = $sut->loadMultiple([321]);
    $entity = $values[123];
    $this->assertInstanceOf(News::class, $entity);
    $this->assertEquals('123', $entity->id());
    $this->assertEquals('uuid-123', $entity->uuid());
    $this->assertEquals('test title', $entity->label());
    $this->assertEquals('https://localhost', $entity->getNodeUrl());
    $this->assertEquals(1234567, $entity->getPublishedAt());
    $this->assertEquals('test shorttitle', $entity->getShortTitle());
  }

}
