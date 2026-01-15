<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_org_chart\Kernel\Entity;

use Drupal\helfi_paragraphs_org_chart\Entity\OrgChart;
use Drupal\KernelTests\KernelTestBase;
use Drupal\helfi_api_base\Features\FeatureManager;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * @coversDefaultClass \Drupal\helfi_paragraphs_org_chart\OrgChartImporter
 *
 * @group helfi_paragraphs_org_chart
 */
class OrgChartParagraphTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'allowed_formats',
    'entity_reference_revisions',
    'field',
    'file',
    'helfi_api_base',
    'user',
    'helfi_paragraphs_org_chart',
    'options',
    'paragraphs',
    'system',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'system',
      'paragraphs',
      'helfi_api_base',
      'helfi_paragraphs_org_chart',
      'options',
    ]);
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('paragraphs_type');
  }

  /**
   * Tests that paragraph uses proper bundle class.
   */
  public function testBundleClass(): void {
    $defaults = [
      'type' => 'org_chart',
      'field_org_chart_title' => 'Test title',
      'field_org_chart_desc' => 'Test description',
      'field_org_chart_start' => '00000',
      'field_org_chart_depth' => 2,
    ];

    $paragraph = Paragraph::create($defaults);
    $paragraph->save();

    $this->assertInstanceOf(OrgChart::class, $paragraph);
    $this->assertEquals($defaults['field_org_chart_title'], $paragraph->getTitle());
    $this->assertEquals($defaults['field_org_chart_desc'], $paragraph->getDescription());
    $this->assertEquals($defaults['field_org_chart_start'], $paragraph->getStartingOrganization());
    $this->assertEquals($defaults['field_org_chart_depth'], $paragraph->getDepth());
  }

  /**
   * The USE_MOCK_RESPONSES feature should be disabled by default.
   */
  public function testFeatureIsEnabledByDefault(): void {
    /** @var \Drupal\helfi_api_base\Features\FeatureManager $service */
    $service = $this->container->get(FeatureManager::class);
    $this->assertFalse($service->isEnabled(FeatureManager::USE_MOCK_RESPONSES));
  }

}
