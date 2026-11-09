<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_media_chart\Kernel\Entity;

use Drupal\Core\Form\FormState;
use Drupal\helfi_media_chart\Entity\HelfiChart;
use Drupal\helfi_media_chart\Form\HelfiChartAddForm;
use Drupal\media_library\MediaLibraryState;
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
    'diff',
    'helfi_api_base',
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

  /**
   * Test invalid url submit.
   */
  public function testSubmitInvalidUrl() : void {
    $formState = new FormState()->set('media_library_state',
      MediaLibraryState::create(
        'media_library.opener.field_widget',
        ['helfi_chart'],
        'helfi_chart',
        1,
      ));
    $formState->setValues(['helfi_chart_url' => 'https://example.com']);

    $form = $this->container
      ->get('class_resolver')
      ->getInstanceFromDefinition(HelfiChartAddForm::class);

    $this->container->get('form_builder')->submitForm($form, $formState);

    $errors = $formState->getErrors();
    $this->assertArrayHasKey('helfi_chart_url', $errors);
    $this->assertStringContainsString('https://example.com', strip_tags((string) $errors['helfi_chart_url']));
    $this->assertEmpty($formState->get('media'));
  }

}
