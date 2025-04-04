<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_chart\Kernel\Entity;

use Drupal\helfi_media_chart\Entity\HelfiChart;
use Drupal\Tests\helfi_media\Kernel\HelfiMediaKernelTestBase;

/**
 * Tests HelfiChart entity bundle class.
 *
 * @group helfi_media_chart
 */
class HelfiChartTest extends HelfiMediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'allowed_formats',
    'helfi_media_chart',
    'language',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('helfi_media_chart');
  }

  /**
   * Tests Helfi Chart bundle class.
   */
  public function testBundleClass(): void {
    $entity = $this->createMediaEntity([
      'name' => 'test',
      'bundle' => 'helfi_chart',
      'field_helfi_chart_url' => [
        'uri' => 'https://playground.powerbi.com/sampleReportEmbed',
      ],
    ]);

    $this->assertInstanceOf(HelfiChart::class, $entity);

    $this->assertEquals('https://playground.powerbi.com', $entity->getServiceUrl());
    $this->assertNull($entity->getMediaTitle());

    $entity->set('field_helfi_chart_title', 'Test title');
    $entity->save();

    $this->assertEquals('Test title', $entity->getMediaTitle());
  }

}
