<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_map\Kernel\Entity;

use Drupal\helfi_media_map\Entity\HelMap;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests HelMap entity bundle class.
 *
 * @group helfi_media_map
 */
class HelpMapTest extends KernelTestBase {

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
  }

  /**
   * Tests Hel Map bundle class.
   */
  public function testBundleClass() : void {
    /** @var \Drupal\media\MediaStorage $storage */
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('media');

    $data = [
      'uri' => 'https://kartta.hel.fi/embed?&setlanguage=fi&link=eptE2g',
    ];
    $entity = $storage->create([
      'name' => 'test',
      'bundle' => 'hel_map',
      'field_media_hel_map' => $data,
    ]);
    $entity->save();
    $this->assertInstanceOf(HelMap::class, $entity);

    $this->assertEquals($entity->getServiceUrl(), 'https://kartta.hel.fi');
    $this->assertNull($entity->getMediaTitle());

    $data['title'] = 'Test title';
    $entity->set('field_media_hel_map', $data);
    $entity->save();

    $this->assertEquals($data['title'], $entity->getMediaTitle());
  }

}
