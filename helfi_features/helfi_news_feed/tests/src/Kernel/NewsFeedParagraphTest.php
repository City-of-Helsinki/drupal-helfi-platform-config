<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_news_feed\Kernel;

use Drupal\external_entities\ExternalEntityStorageInterface;
use Drupal\helfi_news_feed\Entity\NewsFeedParagraph;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\TestLoggerTrait;

/**
 * Tests NewsFeedParagraph installation.
 *
 * @group helfi_news_feed
 */
class NewsFeedParagraphTest extends KernelTestBase {

  use ApiTestTrait;
  use TestLoggerTrait;

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
      ->getStorage('helfi_news_neighbourhoods');
  }

  /**
   * Tests that paragraph uses proper bundle class.
   */
  public function testBundleClass() : void {
    $storage = $this->getExternalEntityStorage();
    $neighbourhood_uuid = '12345678-1234-1234-1234-12345678';
    $neighbourhood = $storage->create([
      'type' => 'helfi_news_neighbourhoods',
      'id' => $neighbourhood_uuid,
      'title' => 'Neighbourhood',
    ]);

    $paragraph = Paragraph::create([
      'type' => 'news_list',
      'field_helfi_news_neighbourhoods' => [$neighbourhood],
      'field_limit' => 22,
      'field_news_list_title' => 'test title',
      'field_news_list_description' => 'test description',
    ]);
    $paragraph->save();

    $this->assertInstanceOf(NewsFeedParagraph::class, $paragraph);
    $this->assertEquals([['target_id' => $neighbourhood_uuid]], $paragraph->getNeighbourhoods());
    $this->assertEquals(22, $paragraph->getLimit());
    $this->assertEquals('test title', $paragraph->getTitle());
    $this->assertEquals('test description', $paragraph->getDescription());
  }

}
