<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_map\Kernel\Entity;

use Drupal\helfi_media_map\Entity\HelMap;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\MediaStorage;

/**
 * Tests HelMap entity bundle class.
 *
 * @group helfi_media_map
 */
class HelpMapTest extends KernelTestBase {

  /** @var \Drupal\media\MediaStorage */
  protected MediaStorage $mediaStorage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'link',
    'path',
    'field',
    'file',
    'image',
    'user',
    'views',
    'media',
    'datetime',
    'media_library',
    'helfi_media',
    'helfi_media_map',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'media', 'media_library']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('media');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig('helfi_media_map');
    $this->mediaStorage = $this->container
      ->get('entity_type.manager')
      ->getStorage('media');
  }

  /**
   * Tests Hel Map bundle class.
   */
  public function testBundleClass() : void {
    // Test the Hel Map bundle class with kartta.hel.fi.
    $this->testMapService(
      ['uri' => 'https://kartta.hel.fi/embed?&setlanguage=fi&link=eptE2g'],
      'https://kartta.hel.fi',
      'Kartta.hel.fi',
    );

    // Test the Hel Map bundle class with palvelukartta.
    $this->testMapService(
      ['uri' => 'https://palvelukartta.hel.fi/fi/embed/?map=servicemap'],
      'https://palvelukartta.hel.fi',
      'Palvelukartta.hel.fi',
      TRUE,
    );
  }

  /**
   * Tests Hel Map bundle class with palvelukartta.
   */
  protected function testMapService(
    array $data,
    string $service_url,
    string $title,
    bool $bypass = FALSE,
  ) : void {
    /** @var \Drupal\media\MediaStorage $storage */
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('media');
    $entity = $storage->create([
      'name' => 'test',
      'bundle' => 'hel_map',
      'field_media_hel_map' => $data,
    ]);
    $entity->save();
    $this->assertInstanceOf(HelMap::class, $entity);

    $this->assertEquals($entity->getServiceUrl(), $service_url);
    $this->assertNull($entity->getMediaTitle());

    $data['title'] = $title;
    $entity->set('field_media_hel_map', $data);
    $entity->save();

    $this->assertEquals($data['title'], $entity->getMediaTitle());
    // Test that the default value of the cookie consent bypass is FALSE
    $this->assertEquals($entity->getCookieConsentBypass(), $bypass);
  }

}
