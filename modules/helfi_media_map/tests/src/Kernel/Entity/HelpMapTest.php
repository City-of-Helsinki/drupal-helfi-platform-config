<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_map\Kernel\Entity;

use Drupal\helfi_media_map\Entity\HelMap;
use Drupal\Tests\helfi_media\Kernel\HelfiMediaKernelTestBase;

/**
 * Tests HelMap entity bundle class.
 *
 * @group helfi_media_map
 */
class HelpMapTest extends HelfiMediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'helfi_media_map',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('helfi_media_map');
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
   * Tests Hel Map bundle class service.
   */
  protected function testMapService(
    array $data,
    string $service_url,
    string $title,
    bool $bypass = FALSE,
  ) : void {
    $entity = $this->createMediaEntity([
      'name' => 'test',
      'bundle' => 'hel_map',
      'field_media_hel_map' => $data,
    ]);

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
