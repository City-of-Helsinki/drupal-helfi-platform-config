<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_chart\Kernel\Entity;

use Drupal\helfi_media_chart\Entity\HelfiChart;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests HelfiChart entity bundle class.
 *
 * @group helfi_media_chart
 */
class HelfiChartTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'allowed_formats',
    'datetime',
    'field',
    'file',
    'helfi_media',
    'helfi_media_chart',
    'image',
    'language',
    'link',
    'media',
    'media_library',
    'path',
    'system',
    'text',
    'user',
    'views',
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
    $this->installConfig('helfi_media_chart');
  }

  /**
   * Tests Helfi Chart bundle class.
   */
  public function testBundleClass() : void {
    /** @var \Drupal\media\MediaStorage $storage */
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('media');

    $data = [
      'uri' => 'https://playground.powerbi.com/sampleReportEmbed',
    ];

    $entity = $storage->create([
      'name' => 'test',
      'bundle' => 'helfi_chart',
      'field_helfi_chart_url' => $data,
    ]);
    $entity->save();
    $this->assertInstanceOf(HelfiChart::class, $entity);

    $this->assertEquals('https://playground.powerbi.com', $entity->getServiceUrl());
    $this->assertNull($entity->getMediaTitle());

    $entity->set('field_helfi_chart_title', 'Test title');
    $entity->save();

    $this->assertEquals('Test title', $entity->getMediaTitle());
  }

}
